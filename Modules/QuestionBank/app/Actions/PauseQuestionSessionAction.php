<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionTimer;
use RuntimeException;

/** Persist the player's resumable cursor/timer state and pause the session. */
final class PauseQuestionSessionAction
{
    use AsAction;

    public function __construct(private readonly QuestionSessionTimer $timer) {}

    /**
     * @param  array<string, mixed>  $pausedState
     */
    public function handle(QuestionSession $session, array $pausedState = []): QuestionSession
    {
        return DB::transaction(function () use ($session, $pausedState): QuestionSession {
            $currentSession = QuestionSession::query()
                ->lockForUpdate()
                ->findOrFail($session->getKey());

            if (! in_array($currentSession->status, [SessionStatus::Active, SessionStatus::Paused], true)) {
                throw new RuntimeException('Chỉ phiên đang hoạt động mới có thể tạm dừng.');
            }

            $remainingSeconds = $this->timer->remainingSeconds($currentSession);
            $nextPausedState = array_merge($currentSession->paused_state ?? [], $pausedState);
            unset($nextPausedState[QuestionSessionTimer::STARTED_AT_KEY]);

            if ($remainingSeconds !== null) {
                $nextPausedState[QuestionSessionTimer::REMAINING_KEY] = $remainingSeconds;
            }

            $currentSession->forceFill([
                'status' => SessionStatus::Paused,
                'paused_state' => $nextPausedState,
            ])->save();

            return $currentSession;
        });
    }
}
