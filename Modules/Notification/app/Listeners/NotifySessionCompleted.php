<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use App\Models\User;
use Modules\Notification\Actions\CreateUserNotificationAction;
use Modules\QuestionBank\Data\QuestionSessionProgressed;
use Modules\QuestionBank\Models\QuestionSession;

/** In-app notification when a Q-Bank session completes. */
final class NotifySessionCompleted
{
    public function __construct(private readonly CreateUserNotificationAction $notify) {}

    public function handle(QuestionSessionProgressed $event): void
    {
        if (! $event->completed) {
            return;
        }

        $user = User::query()->find($event->userId);
        if ($user === null) {
            return;
        }

        $session = QuestionSession::query()->find($event->sessionId);
        $answered = $session?->answered_count ?? 0;
        $total = $session?->total ?? 0;
        $correct = $session?->correct_count ?? 0;

        $this->notify->handle(
            user: $user,
            type: 'session.completed',
            title: 'Hoàn thành phiên Q-Bank',
            body: sprintf('Bạn đã trả lời %d/%d câu, đúng %d câu.', $answered, $total, $correct),
            data: [
                'session_id' => $event->sessionId,
                'answered' => $answered,
                'total' => $total,
                'correct' => $correct,
            ],
        );
    }
}
