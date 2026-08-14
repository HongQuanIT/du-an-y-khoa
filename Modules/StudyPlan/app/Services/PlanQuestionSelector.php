<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Services;

use Illuminate\Support\Collection;
use Modules\Personalization\Models\Bookmark;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Models\Topic;
use Modules\StudyPlan\Enums\TaskType;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Picks the questions that make up a plan task.
 *
 * Question tasks prefer material the learner has not seen; review tasks pull
 * from what they got wrong. Both widen the topic filter when the scope cannot
 * fill the daily goal, so a task never starts empty.
 */
final class PlanQuestionSelector
{
    /**
     * @return array<int, string> question ids, at most `$limit`
     */
    public function forTask(StudyPlanTask $task, int $limit): array
    {
        $userId = $task->plan->user_id;
        $filters = $task->plan->scopeFilters();

        if ($task->type === TaskType::Review) {
            return $this->reviewQuestions($userId, $task, $limit);
        }

        $topicIds = $this->expandTopics($task->topicIds());
        $planTopicIds = $this->expandTopics($task->plan->scopeTopicIds());
        $statuses = $filters['question_statuses'];
        $difficulties = $filters['difficulties'];
        $eligible = $statuses === []
            ? null
            : $this->eligibleQuestionIds($userId, $statuses, $filters['question_status_mode']);

        if ($filters['saved_only']) {
            $bookmarked = Bookmark::questionIdsForUser($userId);
            $eligible = $eligible === null
                ? $bookmarked
                : array_values(array_intersect($eligible, $bookmarked));
        }

        $seen = $eligible === null ? $this->answeredQuestionIds($userId) : [];

        $picked = $this->pick($limit, $topicIds, $seen, $eligible, $difficulties);
        $picked = $this->topUp($picked, $limit, $planTopicIds, $seen, $eligible, $difficulties);
        $picked = $this->topUp($picked, $limit, [], $seen, $eligible, $difficulties);

        // Everything in scope is already answered — fall back to a re-run.
        return $this->topUp($picked, $limit, $planTopicIds, [], $eligible, $difficulties)
            ->shuffle()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function reviewQuestions(int $userId, StudyPlanTask $task, int $limit): array
    {
        $filters = $task->plan->scopeFilters();
        $difficulties = $filters['difficulties'];
        $bookmarked = $filters['saved_only']
            ? Bookmark::questionIdsForUser($userId)
            : null;
        $incorrect = UserQuestionStatusModel::query()
            ->where('user_id', $userId)
            ->where('status', UserQuestionStatus::Incorrect)
            ->when($bookmarked !== null, fn ($query) => $query->whereIn('question_id', $bookmarked))
            ->when(
                $difficulties !== [],
                fn ($query) => $query->whereHas(
                    'question',
                    fn ($questionQuery) => $questionQuery->whereIn('difficulty', $difficulties),
                ),
            )
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('question_id');

        if ($incorrect->count() >= $limit) {
            return $incorrect->shuffle()->values()->all();
        }

        $topUp = $this->topUp(
            $incorrect,
            $limit,
            $this->expandTopics($task->plan->scopeTopicIds()),
            $bookmarked === null ? $this->answeredQuestionIds($userId) : [],
            $bookmarked,
            $difficulties,
        );

        return $topUp->shuffle()->values()->all();
    }

    /**
     * @param  Collection<int, string>  $picked
     * @param  array<int, int>  $topicIds
     * @param  array<int, string>  $exclude
     * @param  array<int, string>|null  $eligible
     * @param  array<int, string>  $difficulties
     * @return Collection<int, string>
     */
    private function topUp(
        Collection $picked,
        int $limit,
        array $topicIds,
        array $exclude,
        ?array $eligible = null,
        array $difficulties = [],
    ): Collection {
        $missing = $limit - $picked->count();

        if ($missing <= 0) {
            return $picked;
        }

        $more = $this->pick(
            $missing,
            $topicIds,
            array_merge($exclude, $picked->all()),
            $eligible,
            $difficulties,
        );

        return $picked->concat($more)->unique()->values();
    }

    /**
     * @param  array<int, int>  $topicIds
     * @param  array<int, string>  $exclude
     * @param  array<int, string>|null  $eligible
     * @param  array<int, string>  $difficulties
     * @return Collection<int, string>
     */
    private function pick(
        int $limit,
        array $topicIds,
        array $exclude,
        ?array $eligible = null,
        array $difficulties = [],
    ): Collection {
        if ($limit <= 0) {
            return collect();
        }

        return Question::query()
            ->where('status', QuestionStatus::Published)
            ->when($topicIds !== [], fn ($query) => $query->whereIn('topic_id', $topicIds))
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('id', $exclude))
            ->when($eligible !== null, fn ($query) => $query->whereIn('id', $eligible))
            ->when($difficulties !== [], fn ($query) => $query->whereIn('difficulty', $difficulties))
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('id');
    }

    /**
     * A specialty in the scope also covers the systems beneath it.
     *
     * @param  array<int, int>  $topicIds
     * @return array<int, int>
     */
    private function expandTopics(array $topicIds): array
    {
        if ($topicIds === []) {
            return [];
        }

        $children = Topic::query()
            ->whereIn('parent_id', $topicIds)
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($topicIds, $children)));
    }

    /**
     * @return array<int, string>
     */
    private function answeredQuestionIds(int $userId): array
    {
        return UserQuestionStatusModel::query()
            ->where('user_id', $userId)
            ->whereIn('status', [UserQuestionStatus::Correct, UserQuestionStatus::Incorrect])
            ->pluck('question_id')
            ->all();
    }

    /**
     * Resolve the multi-select status filter to question ids.
     *
     * @param  array<int, string>  $statuses
     * @return array<int, string>
     */
    private function eligibleQuestionIds(int $userId, array $statuses, string $mode): array
    {
        $attempts = QuestionAttempt::query()
            ->where('user_id', $userId)
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->get(['question_id', 'is_correct', 'used_hint']);

        $evaluated = $mode === 'latest'
            ? $attempts->unique('question_id')->values()
            : $attempts;

        $eligible = $evaluated
            ->filter(function (QuestionAttempt $attempt) use ($statuses): bool {
                return match (true) {
                    $attempt->is_correct === false => in_array('incorrect', $statuses, true),
                    $attempt->is_correct === true && $attempt->used_hint => in_array('correct_with_hints', $statuses, true),
                    $attempt->is_correct === true => in_array('correct', $statuses, true),
                    default => false,
                };
            })
            ->pluck('question_id');

        if (in_array('unanswered', $statuses, true)) {
            $answered = $attempts->pluck('question_id')->unique()->all();
            $unanswered = Question::query()
                ->where('status', QuestionStatus::Published)
                ->when($answered !== [], fn ($query) => $query->whereNotIn('id', $answered))
                ->pluck('id');
            $eligible = $eligible->concat($unanswered);
        }

        return $eligible->unique()->values()->all();
    }
}
