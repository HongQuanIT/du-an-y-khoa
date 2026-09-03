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
    ) {}

    public static function stopCacheKey(string $messageId): string
    {
        return 'ai:stop:'.$messageId;
    }

    public function handle(AiThread $thread, AiMessage $assistant, bool $broadcast): AiMessage
    {
        $userId = (int) $thread->user_id;

        if ($broadcast) {
            $this->emit($assistant, AiStreamEvent::START, ['role' => 'assistant']);
        }

        $assistant->update(['status' => AiMessage::STATUS_STREAMING]);

        try {
            [$system, $history] = $this->buildPrompt($thread, $assistant);

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

            return $this->finish($thread, $assistant, $reply, $broadcast);
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

    private function finish(AiThread $thread, AiMessage $assistant, TutorReply $reply, bool $broadcast): AiMessage
    {
        $assistant->update([
            'status' => $reply->stopped ? AiMessage::STATUS_STOPPED : AiMessage::STATUS_DONE,
            'content' => $reply->content,
            'citations' => $reply->citations,
            'tokens_in' => $reply->tokensIn,
            'tokens_out' => $reply->tokensOut,
        ]);

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
     * @return array{0: string, 1: array<int, array{role: string, content: string}>}
     */
    private function buildPrompt(AiThread $thread, AiMessage $assistant): array
    {
        $preset = $thread->preset ? TutorPreset::tryFrom($thread->preset) : null;
        $pack = [];

        if ($thread->context_type === 'question' && $thread->session_id && $thread->context_id) {
            $session = QuestionSession::query()->find($thread->session_id);
            if ($session instanceof QuestionSession) {
                $built = $this->contextBuilder->forQuestion(
                    $thread->user,
                    $session,
                    (string) $thread->context_id,
                    $preset,
                );
                $pack = $built['pack'];
            }
        }

        $system = $this->prompts->systemPrompt($pack, $preset ?? TutorPreset::AnalyzeWithoutSpoiler);

        $history = $thread->messages()
            ->where('id', '!=', $assistant->getKey())
            ->whereIn('role', [AiMessage::ROLE_USER, AiMessage::ROLE_ASSISTANT])
            ->whereIn('status', [AiMessage::STATUS_DONE, AiMessage::STATUS_STOPPED])
            ->orderBy('created_at')
            ->get()
            ->map(fn (AiMessage $m): array => [
                'role' => $m->role,
                'content' => (string) $m->content,
            ])
            ->values()
            ->all();

        // The just-created user message is `done` by default, so it is included above.
        return [$system, $history];
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
