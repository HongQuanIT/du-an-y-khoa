<?php

declare(strict_types=1);

namespace Modules\Exam\Services;

use App\Models\User;
use App\Support\TargetExams;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\SessionQuestionSelector;

final class ExamCatalogService
{
    public function __construct(private readonly SessionQuestionSelector $selector) {}

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     icon: string,
     *     hint: string,
     *     available_count: int,
     *     default_count: int,
     *     estimated_minutes: int
     * }>
     */
    public function cards(User $user): array
    {
        $cards = [];

        foreach (TargetExams::selectable() as $key => $exam) {
            $available = $this->availableCount($user, (string) $key);
            $defaultCount = min(40, max(1, $available));

            $cards[] = [
                'key' => (string) $key,
                'title' => (string) $exam['title'],
                'icon' => (string) $exam['icon'],
                'hint' => (string) $exam['hint'],
                'available_count' => $available,
                'default_count' => $defaultCount,
                'estimated_minutes' => (int) ceil($defaultCount * 90 / 60),
            ];
        }

        return $cards;
    }

    /**
     * @return Collection<int, QuestionSession>
     */
    public function recentSessions(User $user): Collection
    {
        return QuestionSession::query()
            ->where('user_id', $user->getKey())
            ->where('mode', SessionMode::Exam)
            ->where('source', SessionSource::Exam)
            ->latest('updated_at')
            ->limit(6)
            ->get();
    }

    private function availableCount(User $user, string $examKey): int
    {
        return $this->selector->countForSession($user, new CreateSessionData(
            mode: SessionMode::Exam,
            source: SessionSource::Exam,
            count: 10_000,
            examKey: $examKey,
        ));
    }
}
