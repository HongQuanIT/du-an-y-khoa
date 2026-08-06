<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionTimer;
use RuntimeException;

/** Reactivate a paused session while retaining its restoration snapshot. */
final class ResumeQuestionSessionAction
{
    use AsAction;

    public function __construct(private readonly QuestionSessionTimer $timer) {}

    public function handle(QuestionSession $session): QuestionSession
    {
        return DB::transaction(function () use ($session): QuestionSession {
            $currentSession = QuestionSession::query()
                ->lockForUpdate()
                ->findOrFail($session->getKey());

            if ($currentSession->status === SessionStatus::Active) {
                return $currentSession;
            }

            if ($currentSession->status !== SessionStatus::Paused) {
                throw new RuntimeException('Chỉ phiên đang tạm dừng mới có thể tiếp tục.');
            }

            $remainingSeconds = $this->timer->remainingSeconds($currentSession);
            $nextPausedState = $currentSession->paused_state ?? [];

            if ($remainingSeconds !== null) {
                $nextPausedState[QuestionSessionTimer::REMAINING_KEY] = $remainingSeconds;
                $nextPausedState[QuestionSessionTimer::STARTED_AT_KEY] = now()->toIso8601String();
            }

            $currentSession->forceFill([
                'status' => SessionStatus::Active,
                'paused_state' => $nextPausedState,
            ])->save();

            return $currentSession;
        });
    }
}
