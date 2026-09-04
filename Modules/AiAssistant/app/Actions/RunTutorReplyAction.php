<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\AiAssistant\Contracts\AiTutorClient;
use Modules\AiAssistant\Contracts\TutorReply;
use Modules\AiAssistant\Enums\TutorPreset;
use Modules\AiAssistant\Events\AiStreamEvent;
use Modules\AiAssistant\Models\AiMessage;
use Modules\AiAssistant\Models\AiThread;
use Modules\AiAssistant\Services\AiQuotaService;
use Modules\AiAssistant\Services\ContextPackBuilder;
use Modules\AiAssistant\Services\TutorPromptFactory;
use Modules\AiAssistant\Services\TutorResponseCache;
use Modules\AiAssistant\Support\AiTutorSettings;
use Modules\QuestionBank\Models\QuestionSession;
use Throwable;

/**
 * Generates the assistant reply for a pending message. Shared by the queued
 * job (broadcast=true, streams over Reverb) and the synchronous controller
 * fallback (broadcast=false, returns the full text) so both paths behave
 * identically. Quota is refunded here if the provider fails after being charged.
 */
final class RunTutorReplyAction
{
    public function __construct(
        private readonly AiTutorClient $client,
        private readonly ContextPackBuilder $contextBuilder,
        private readonly TutorPromptFactory $prompts,
        private readonly AiQuotaService $quota,
        private readonly TutorResponseCache $responseCache,
    ) {}

    public static function stopCacheKey(string $messageId): string
    {
        return 'ai:stop:'.$messageId;
    }

    public function handle(AiThread $thread, AiMessage $assistant, bool $broadcast): AiMessage
    {
        if ($broadcast) {
            $this->emit($assistant, AiStreamEvent::START, ['role' => 'assistant']);
        }

        $assistant->update(['status' => AiMessage::STATUS_STREAMING]);

        try {
            [$system, $history, $pack, $cacheable] = $this->buildPrompt($thread, $assistant);

            if ($cacheable) {
                $cached = $this->responseCache->get($thread, $pack);
                if ($cached !== null) {
                    $reply = new TutorReply(
                        content: $cached['content'],
                        citations: $cached['citations'],
                        tokensIn: 0,
                        tokensOut: 0,
                    );

                    if ($broadcast && $reply->content !== '') {
                        $this->emit($assistant, AiStreamEvent::DELTA, ['delta' => $reply->content]);
                    }

                    return $this->finish($thread, $assistant, $reply, $broadcast, storeCache: false, pack: $pack);
                }
            }

            $reply = $this->client->stream(
                $system,
                $history,
                function (string $delta) use ($assistant, $broadcast): void {
                    if ($broadcast && $delta !== '') {
                        $this->emit($assistant, AiStreamEvent::DELTA, ['delta' => $delta]);
                    }
                },
                fn (): bool => Cache::get(self::stopCacheKey($assistant->getKey()), false) === true,
            );

            return $this->finish(
                $thread,
                $assistant,
                $reply,
                $broadcast,
                storeCache: $cacheable && ! $reply->stopped,
                pack: $pack,
            );
        } catch (Throwable $e) {
            $user = $thread->user;
            if ($user instanceof User) {
                $this->quota->refund($user);
            }

            $assistant->update([
                'status' => AiMessage::STATUS_FAILED,
                'content' => (string) $assistant->content,
            ]);

            if ($broadcast) {
                $this->emit($assistant, AiStreamEvent::ERROR, [
                    'code' => 'PROVIDER_ERROR',
                    'message' => 'AI Tutor đang bận. Giữ nguyên câu hỏi để thử lại.',
                ]);
            }

            throw $e;
        } finally {
            Cache::forget(self::stopCacheKey($assistant->getKey()));
        }
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    private function finish(
        AiThread $thread,
        AiMessage $assistant,
        TutorReply $reply,
        bool $broadcast,
        bool $storeCache,
        array $pack,
    ): AiMessage {
        $assistant->update([
            'status' => $reply->stopped ? AiMessage::STATUS_STOPPED : AiMessage::STATUS_DONE,
            'content' => $reply->content,
            'citations' => $reply->citations,
            'tokens_in' => $reply->tokensIn,
            'tokens_out' => $reply->tokensOut,
        ]);

        if ($storeCache && ! $reply->stopped && trim($reply->content) !== '') {
            $this->responseCache->put($thread, $pack, $reply->content, $reply->citations);
        }

        if ($broadcast) {
            if ($reply->citations !== []) {
                $this->emit($assistant, AiStreamEvent::CITATION, ['citations' => $reply->citations]);
            }

            $user = $thread->user;
            $this->emit($assistant, AiStreamEvent::DONE, [
                'content' => $reply->content,
                'citations' => $reply->citations,
                'status' => $assistant->status,
                'quota' => $user instanceof User ? $this->quota->snapshot($user) : null,
            ]);
        }

        return $assistant;
    }

    /**
     * @return array{0: list<string>, 1: array<int, array{role: string, content: string}>, 2: array<string, mixed>, 3: bool}
     */
    private function buildPrompt(AiThread $thread, AiMessage $assistant): array
    {
        $preset = $thread->preset ? TutorPreset::tryFrom($thread->preset) : null;
        $pack = $this->resolvePack($thread);

        $priorAssistantDone = $thread->messages()
            ->where('id', '!=', $assistant->getKey())
            ->where('role', AiMessage::ROLE_ASSISTANT)
            ->whereIn('status', [AiMessage::STATUS_DONE, AiMessage::STATUS_STOPPED])
            ->exists();

        $includeFullContext = ! $priorAssistantDone;
        $system = $this->prompts->systemMessages(
            $pack,
            $preset ?? TutorPreset::AnalyzeWithoutSpoiler,
            $includeFullContext,
        );

        $history = $this->truncateHistory($thread, $assistant);

        $latestUser = $this->latestUserContent($history);
        $cacheable = $includeFullContext
            && $this->responseCache->isCacheableAutoStart($thread, $latestUser, $pack);

        return [$system, $history, $pack, $cacheable];
    }

    /** @return array<string, mixed> */
    private function resolvePack(AiThread $thread): array
    {
        if ($thread->context_type === 'question' && $thread->session_id && $thread->context_id) {
            $session = QuestionSession::query()->find($thread->session_id);
            if ($session instanceof QuestionSession) {
                $preset = $thread->preset ? TutorPreset::tryFrom($thread->preset) : null;

                return $this->contextBuilder->forQuestion(
                    $thread->user,
                    $session,
                    (string) $thread->context_id,
                    $preset,
                )['pack'];
            }
        }

        // Minimal pack so follow-ups still get CONTEXT_REF when session snapshot is unavailable.
        if ($thread->context_id !== null) {
            return [
                'question_id' => (string) $thread->context_id,
                'answered' => false,
            ];
        }

        return [];
    }

    /**
     * Newest N user/assistant messages; always keeps the latest user turn.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function truncateHistory(AiThread $thread, AiMessage $assistant): array
    {
        $max = AiTutorSettings::historyMaxMessages();

        $rows = AiMessage::query()
            ->where('thread_id', $thread->getKey())
            ->where('id', '!=', $assistant->getKey())
            ->whereIn('role', [AiMessage::ROLE_USER, AiMessage::ROLE_ASSISTANT])
            ->whereIn('status', [AiMessage::STATUS_DONE, AiMessage::STATUS_STOPPED])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($max)
            ->get()
            ->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $hasUser = $rows->contains(fn (AiMessage $m): bool => $m->role === AiMessage::ROLE_USER);
        if (! $hasUser) {
            $latestUser = AiMessage::query()
                ->where('thread_id', $thread->getKey())
                ->where('id', '!=', $assistant->getKey())
                ->where('role', AiMessage::ROLE_USER)
                ->where('status', AiMessage::STATUS_DONE)
                ->orderByDesc('created_at')
                ->first();
            if ($latestUser !== null) {
                $rows = $rows->push($latestUser)->sortBy([
                    ['created_at', 'asc'],
                    ['id', 'asc'],
                ])->values();
                if ($rows->count() > $max) {
                    $rows = $rows->slice(-$max)->values();
                }
            }
        }

        return $rows
            ->map(fn (AiMessage $m): array => [
                'role' => $m->role,
                'content' => (string) $m->content,
            ])
            ->all();
    }

    /** @param array<int, array{role: string, content: string}> $history */
    private function latestUserContent(array $history): string
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['role'] ?? '') === AiMessage::ROLE_USER) {
                return (string) ($history[$i]['content'] ?? '');
            }
        }

        return '';
    }

    /** @param array<string, mixed> $payload */
    private function emit(AiMessage $assistant, string $type, array $payload): void
    {
        event(new AiStreamEvent(
            userId: (int) $assistant->user_id,
            threadId: (string) $assistant->thread_id,
            messageId: (string) $assistant->getKey(),
            type: $type,
            payload: $payload,
        ));
    }
}
