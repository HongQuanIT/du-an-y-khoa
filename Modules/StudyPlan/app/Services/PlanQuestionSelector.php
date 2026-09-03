<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Personalization\Models\Bookmark;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
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
        $assigned = array_values(array_unique(array_map(
            'strval',
            (array) ($task->ref['question_ids'] ?? []),
        )));
        if ($task->type === TaskType::Questions && $assigned !== []) {
            return array_slice($assigned, 0, $limit);
        }

        $userId = $task->plan->user_id;
        $filters = $task->plan->scopeFilters();

        if ($task->type === TaskType::Review) {
            return $this->reviewQuestions($userId, $task, $limit);
        }

        $topicIds = $this->expandNodes($task->medicalTaxonomyNodeIds());
        $planTopicIds = $this->expandNodes($task->plan->scopeMedicalTaxonomyNodeIds());
        $statuses = $filters['question_statuses'];
        $difficulties = $filters['difficulties'];
        $eligible = $statuses === []
            ? null
            : $this->eligibleQuestionIds($userId, $statuses, $filters['question_status_mode']);

        // savedOnly: intersect applied as a DB subquery in pick(), not in PHP.
        $savedForUserId = $filters['saved_only'] ? $userId : null;

        if ($eligible !== null && $savedForUserId !== null) {
            // Pre-filter eligible IDs against bookmark IDs to keep the
            // WHERE IN list small; the subquery covers the rest.
            $bookmarked = Bookmark::questionIdsForUser($userId);
            $eligible = array_values(array_intersect($eligible, $bookmarked));
            $savedForUserId = null; // subquery already embedded in eligible
        }

        $seen = ($eligible === null && $savedForUserId === null)
            ? $this->answeredQuestionIds($userId)
            : [];

        $picked = $this->pick($limit, $topicIds, $seen, $eligible, $difficulties, $savedForUserId);
        $picked = $this->topUp($picked, $limit, $planTopicIds, $seen, $eligible, $difficulties, $savedForUserId);
        $picked = $this->topUp($picked, $limit, [], $seen, $eligible, $difficulties, $savedForUserId);

        // Everything in scope is already answered — fall back to a re-run.
        return $this->topUp($picked, $limit, $planTopicIds, [], $eligible, $difficulties, $savedForUserId)
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
        // Apply saved-only as a DB subquery rather than loading all IDs into PHP.
        $savedForUserId = $filters['saved_only'] ? $userId : null;
        $incorrect = UserQuestionStatusModel::query()
            ->where('user_id', $userId)
            ->where('status', UserQuestionStatus::Incorrect)
            ->when(
                $savedForUserId !== null,
                fn ($query) => $query->whereIn(
                    'question_id',
                    Bookmark::bookmarkSubquery($savedForUserId),
                )
            )
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
            $this->expandNodes($task->plan->scopeMedicalTaxonomyNodeIds()),
            $savedForUserId === null ? $this->answeredQuestionIds($userId) : [],
            null,
            $difficulties,
            $savedForUserId,
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
        ?int $savedForUserId = null,
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
            $savedForUserId,
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
        ?int $savedForUserId = null,
    ): Collection {
        if ($limit <= 0) {
            return collect();
        }

        return Question::query()
            ->where('status', QuestionStatus::Published)
            ->when(
                $topicIds !== [],
                fn ($query) => $query->whereHas(
                    'medicalTaxonomyNodes',
                    fn (Builder $nodes) => $nodes->whereIn('medical_taxonomy_nodes.id', $topicIds),
                ),
            )
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('id', $exclude))
            ->when($eligible !== null, fn ($query) => $query->whereIn('id', $eligible))
            ->when($difficulties !== [], fn ($query) => $query->whereIn('difficulty', $difficulties))
            ->when(
                $savedForUserId !== null,
                fn ($query) => $query->whereIn('id', Bookmark::bookmarkSubquery($savedForUserId))
            )
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
    private function expandNodes(array $topicIds): array
    {
        if ($topicIds === []) {
            return [];
        }

        $children = MedicalTaxonomyNode::query()
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
