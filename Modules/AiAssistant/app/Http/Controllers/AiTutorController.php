<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\AiAssistant\Actions\RunTutorReplyAction;
use Modules\AiAssistant\Enums\TutorPreset;
use Modules\AiAssistant\Jobs\StreamAiTutorReplyJob;
use Modules\AiAssistant\Models\AiMessage;
use Modules\AiAssistant\Models\AiThread;
use Modules\AiAssistant\Services\AiQuotaService;
use Modules\AiAssistant\Services\ContextPackBuilder;
use Modules\AiAssistant\Services\TutorPromptFactory;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;

final class AiTutorController extends Controller
{
    public function __construct(
        private readonly AiQuotaService $quota,
        private readonly ContextPackBuilder $contextBuilder,
        private readonly TutorPromptFactory $prompts,
    ) {}

    public function quota(Request $request): JsonResponse
    {
        return ApiResponse::item($this->quota->snapshot($request->user()));
    }

    public function storeThread(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'context' => ['nullable', 'array'],
            'context.type' => ['required_with:context', 'string', 'in:question,article,disease,drug,procedure'],
            'context.id' => ['required_with:context', 'string'],
            'context.session_id' => ['nullable', 'string'],
            'context.source' => ['required_with:context', 'string', 'in:session,review,library'],
            'auto_start' => ['nullable', 'boolean'],
            'selection' => ['nullable', 'string', 'max:500'],
        ]);

        $context = $validated['context'] ?? null;
        $selection = $validated['selection'] ?? null;

        if (! $this->quota->hasQuota($user)) {
            return $this->quotaExceeded();
        }

        // Context-less thread (free chat on /ai): no auto-start, no spoiler pack.
        if ($context === null) {
            $thread = AiThread::query()->create([
                'user_id' => $user->getKey(),
                'title' => 'Hỏi đáp AI Tutor',
                'context_source' => 'ai',
            ]);

            return $this->threadResponse($thread, '', $user, 201);
        }

        // Idempotent auto-start: same user + key within the TTL reuses the thread
        // so a page reload / double tap never creates a second thread.
        $idemKey = trim((string) $request->header('Idempotency-Key'));
        if ($idemKey !== '') {
            $cacheKey = 'ai:idem:thread:'.$user->getKey().':'.sha1($idemKey);
            $existingId = Cache::get($cacheKey);
            if (is_string($existingId) && ($existing = AiThread::query()->find($existingId)) !== null) {
                return $this->threadResponse($existing, $this->threadAutoPrompt($existing, $selection), $user, 200);
            }
        }

        // Resolve context (question path only for now) + spoiler gating.
        $session = null;
        $built = ['found' => false, 'answered' => false, 'is_correct' => null, 'label' => 'Câu hỏi', 'pack' => []];

        if ($context['type'] === 'question') {
            $session = QuestionSession::query()->find($context['session_id'] ?? null);
            if ($session === null) {
                return ApiResponse::error('VALIDATION_ERROR', 'Phiên không hợp lệ.', 422);
            }

            $this->authorize('view', $session);

            // Exam in progress must never expose the answer through the tutor.
            if ($session->mode === SessionMode::Exam
                && in_array($session->status, [SessionStatus::Active, SessionStatus::Paused], true)) {
                return ApiResponse::error('EXAM_LOCKED', 'AI Tutor không khả dụng trong khi thi.', 403);
            }
        }

        $preset = $this->contextBuilder->decidePreset(
            $context['source'],
            false,
            null,
            $selection,
        );

        if ($session !== null) {
            $built = $this->contextBuilder->forQuestion($user, $session, (string) $context['id'], null);
            if (! $built['found']) {
                return ApiResponse::error('NOT_FOUND', 'Không tìm thấy câu hỏi.', 404);
            }
            $preset = $this->contextBuilder->decidePreset(
                $context['source'],
                $built['answered'],
                $built['is_correct'],
                $selection,
            );
        }

        $thread = AiThread::query()->create([
            'user_id' => $user->getKey(),
            'title' => $built['label'],
            'context_type' => $context['type'],
            'context_id' => $context['id'],
            'context_source' => $context['source'],
            'session_id' => $context['session_id'] ?? null,
            'preset' => $preset->value,
        ]);

        if ($idemKey !== '') {
            Cache::put(
                'ai:idem:thread:'.$user->getKey().':'.sha1($idemKey),
                $thread->getKey(),
                (int) config('aiassistant.idempotency_ttl', 120),
            );
        }

        $autoPrompt = $this->prompts->autoPromptContent($preset, $built['pack'], $selection);

        return $this->threadResponse($thread, $autoPrompt, $user, 201);
    }

    public function storeMessage(Request $request, AiThread $thread): JsonResponse
    {
        $this->authorize('update', $thread);
        $user = $request->user();

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:4000'],
            'preset' => ['nullable', 'string'],
        ]);

        // Message-level idempotency: a retried POST returns the same assistant
        // message instead of charging quota twice.
        $idemKey = trim((string) $request->header('Idempotency-Key'));
        $idemCacheKey = $idemKey !== ''
            ? 'ai:idem:msg:'.$user->getKey().':'.sha1($idemKey)
            : null;

        if ($idemCacheKey !== null) {
            $existingId = Cache::get($idemCacheKey);
            if (is_string($existingId) && ($existing = AiMessage::query()->find($existingId)) !== null) {
                return $this->messageResponse($existing->thread, $existing, $user, 200);
            }
        }

        if (! $this->quota->hasQuota($user)) {
            return $this->quotaExceeded();
        }

        // Charge one unit up-front; refunded by the action if the provider fails.
        $this->quota->consume($user);

        $userMessage = $thread->messages()->create([
            'user_id' => $user->getKey(),
            'role' => AiMessage::ROLE_USER,
            'status' => AiMessage::STATUS_DONE,
            'preset' => $validated['preset'] ?? null,
            'content' => $validated['content'],
        ]);

        $assistant = $thread->messages()->create([
            'user_id' => $user->getKey(),
            'role' => AiMessage::ROLE_ASSISTANT,
            'status' => AiMessage::STATUS_PENDING,
            'preset' => $validated['preset'] ?? null,
            'content' => '',
        ]);

        if ($idemCacheKey !== null) {
            Cache::put($idemCacheKey, $assistant->getKey(), (int) config('aiassistant.idempotency_ttl', 120));
        }

        if ($this->broadcastingEnabled()) {
            StreamAiTutorReplyJob::dispatch($thread->getKey(), $assistant->getKey());

            return ApiResponse::item([
                'user_message' => $userMessage->toApiArray(),
                'assistant_message_id' => $assistant->getKey(),
                'streaming' => true,
                'quota' => $this->quota->snapshot($user),
            ], 202);
        }

        // No realtime driver configured — generate synchronously and return the
        // full text so the drawer still works end-to-end.
        app(RunTutorReplyAction::class)->handle($thread, $assistant->fresh(), broadcast: false);

        return ApiResponse::item([
            'user_message' => $userMessage->toApiArray(),
            'assistant_message' => $assistant->fresh()->toApiArray(),
            'streaming' => false,
            'quota' => $this->quota->snapshot($user),
        ]);
    }

    public function showThread(Request $request, AiThread $thread): JsonResponse
    {
        $this->authorize('view', $thread);

        return ApiResponse::item([
            'id' => $thread->getKey(),
            'title' => $thread->title,
            'preset' => $thread->preset,
            'messages' => $thread->messages->map(fn (AiMessage $m): array => $m->toApiArray())->all(),
        ]);
    }

    public function stop(Request $request, AiThread $thread): JsonResponse
    {
        $this->authorize('update', $thread);

        $validated = $request->validate(['message_id' => ['required', 'string']]);

        $message = $thread->messages()->where('id', $validated['message_id'])->first();
        if ($message !== null) {
            Cache::put(RunTutorReplyAction::stopCacheKey($message->getKey()), true, 120);
        }

        return ApiResponse::item(['stopped' => true]);
    }

    public function feedback(Request $request, AiMessage $message): JsonResponse
    {
        abort_unless((int) $message->user_id === (int) $request->user()->getKey(), 403);
        abort_unless($message->role === AiMessage::ROLE_ASSISTANT, 422, 'Chỉ đánh giá tin của AI Tutor.');

        $validated = $request->validate(['vote' => ['required', 'in:up,down']]);
        $message->update(['feedback_vote' => $validated['vote']]);

        return ApiResponse::item(['vote' => $message->feedback_vote]);
    }

    private function broadcastingEnabled(): bool
    {
        return in_array((string) config('broadcasting.default'), ['reverb', 'pusher', 'ably'], true);
    }

    private function threadAutoPrompt(AiThread $thread, ?string $selection): string
    {
        $preset = $thread->preset ? TutorPreset::tryFrom($thread->preset) : null;
        $pack = [];

        if ($thread->context_type === 'question' && $thread->session_id && $thread->context_id) {
            $session = QuestionSession::query()->find($thread->session_id);
            if ($session !== null) {
                $pack = $this->contextBuilder->forQuestion($thread->user, $session, (string) $thread->context_id, $preset)['pack'];
            }
        }

        return $this->prompts->autoPromptContent($preset ?? TutorPreset::AnalyzeWithoutSpoiler, $pack, $selection);
    }

    private function threadResponse(AiThread $thread, string $autoPrompt, $user, int $status): JsonResponse
    {
        return ApiResponse::item([
            'id' => $thread->getKey(),
            'title' => $thread->title,
            'context' => [
                'type' => $thread->context_type,
                'id' => $thread->context_id,
                'label' => $thread->title,
            ],
            'preset' => $thread->preset,
            'auto_prompt' => ['content' => $autoPrompt],
            'quota' => $this->quota->snapshot($user),
        ], $status);
    }

    private function messageResponse(AiThread $thread, AiMessage $assistant, $user, int $status): JsonResponse
    {
        return ApiResponse::item([
            'assistant_message' => $assistant->toApiArray(),
            'assistant_message_id' => $assistant->getKey(),
            'streaming' => false,
            'quota' => $this->quota->snapshot($user),
        ], $status);
    }

    private function quotaExceeded(): JsonResponse
    {
        $user = request()->user();
        $message = $user !== null && $this->quota->isPremium($user)
            ? 'Bạn đã dùng hết lượt AI Tutor hôm nay. Quota sẽ reset vào ngày mai.'
            : 'Bạn đã dùng hết lượt AI Tutor hôm nay. Nâng cấp để dùng nhiều hơn.';

        return ApiResponse::error(
            'QUOTA_EXCEEDED',
            $message,
            429,
            [$this->quota->snapshot($user)],
        );
    }
}
