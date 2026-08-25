<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;

final class ListRecentLearningActivitiesAction
{
    use AsAction;

    /** @return list<array{icon: string, tone: string, title: string, detail: string, time: string, url: string}> */
    public function handle(User $user, int $limit = 5): array
    {
        return QuestionSession::query()
            ->where('user_id', $user->getKey())
            ->where('status', SessionStatus::Completed)
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (QuestionSession $session): array {
                $rate = $session->answered_count > 0
                    ? (int) round($session->correct_count / $session->answered_count * 100)
                    : 0;

                return [
                    'icon' => $session->mode === SessionMode::Exam ? 'assignment' : 'quiz',
                    'tone' => $session->mode === SessionMode::Exam ? 'secondary' : 'primary',
                    'title' => 'Hoàn thành '.lcfirst($session->displayName()),
                    'detail' => sprintf('Đúng %d/%d câu (%d%%)', $session->correct_count, $session->answered_count, $rate),
                    'time' => $session->updated_at->locale('vi')->diffForHumans(),
                    'url' => route(
                        $session->mode === SessionMode::Exam ? 'exam.summary' : 'qbank.summary',
                        $session,
                    ),
                ];
            })
            ->values()
            ->all();
    }
}
