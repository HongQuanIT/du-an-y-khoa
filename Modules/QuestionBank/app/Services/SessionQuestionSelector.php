<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use App\Models\User;
use App\Support\Enums\Entitlement;
use App\Support\TargetExams;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Personalization\Models\Bookmark;
use Modules\Personalization\Models\BookmarkFolderItem;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionScopeType;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Models\Topic;

/**
 * Picks published questions for a custom / exam / weak-topics session.
 */
final class SessionQuestionSelector
{
    /**
     * @return array<int, string>
     */
    public function forSession(User $user, CreateSessionData $data): array
    {
        $userId = (int) $user->getKey();
        $canUsePremium = $user->hasEntitlement(Entitlement::QbankFull->value);

        if ($data->source === SessionSource::WeakTopics) {
            return $this->weakTopicQuestions($userId, $data->count, $canUsePremium);
        }

        if ($data->examId !== null) {
            return DB::table('exam_question')
                ->where('exam_id', $data->examId)
                ->orderBy('order')
                ->pluck('question_id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        $topicIds = $this->expandTopics($data->topicIds);
        $eligible = $this->eligibleForData($userId, $data, $canUsePremium);

        $difficulties = $this->parseDifficulties($data->difficulties);

        // A custom filter is a hard boundary. Returning fewer questions is
        // preferable to silently pulling unrelated material from other topics.
        $picked = $this->pick(
            $data->count,
            $topicIds,
            [],
            $eligible,
            $difficulties,
            $canUsePremium,
            $data,
            $userId,
        );

        return $picked->shuffle()->values()->all();
    }

    /** Count the full accessible pool using the exact creation filters. */
    public function countForSession(User $user, CreateSessionData $data): int
    {
        $userId = (int) $user->getKey();
        $canUsePremium = $user->hasEntitlement(Entitlement::QbankFull->value);

        if ($data->source === SessionSource::WeakTopics) {
            $topicIds = $this->expandTopics($this->weakTopicIds($userId));

            return $this->questionQuery(
                $topicIds,
                [],
                null,
                [],
                $canUsePremium,
            )->count();
        }

        if ($data->examId !== null) {
            return DB::table('exam_question')->where('exam_id', $data->examId)->count();
        }

        return $this->questionQuery(
            $this->expandTopics($data->topicIds),
            [],
            $this->eligibleForData($userId, $data, $canUsePremium),
            $this->parseDifficulties($data->difficulties),
            $canUsePremium,
            $data,
            $userId,
        )->count();
    }

    /**
     * @return array<int, string>|null
     */
    private function eligibleForData(
        int $userId,
        CreateSessionData $data,
        bool $canUsePremium,
    ): ?array {
        // savedOnly is applied as a DB subquery inside questionQuery();
        // we only resolve question_status IDs here.
        return $data->questionStatuses === []
            ? null
            : $this->eligibleQuestionIds(
                $userId,
                $data->questionStatuses,
                $data->questionStatusMode,
                $canUsePremium,
            );
    }

    /**
     * @return array<int, string>
     */
    private function weakTopicQuestions(int $userId, int $limit, bool $canUsePremium): array
    {
        $weakTopicIds = $this->weakTopicIds($userId);
        $expandedTopicIds = $this->expandTopics($weakTopicIds);

        $accessibleQuestions = Question::query()
            ->select('id')
            ->where('status', QuestionStatus::Published)
            ->when(! $canUsePremium, fn ($query) => $query->where('is_free', true))
            ->when(
                $expandedTopicIds !== [],
                fn ($query) => $query->whereHas(
                    'topics',
                    fn (Builder $topics) => $topics->whereIn('topics.id', $expandedTopicIds),
                ),
            );

        $incorrect = UserQuestionStatusModel::query()
            ->where('user_id', $userId)
            ->where('status', UserQuestionStatus::Incorrect)
            ->whereIn('question_id', $accessibleQuestions)
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('question_id');

        if ($incorrect->count() >= $limit) {
            return $incorrect->shuffle()->values()->all();
        }

        return $this->topUp(
            $incorrect,
            $limit,
            $expandedTopicIds,
            [],
            null,
            [],
            $canUsePremium,
        )->shuffle()->values()->all();
    }

    /**
     * Calculate weak topics directly from Q-Bank attempts. This keeps the
     * QuestionBank module independent from Analytics rollup models.
     *
     * @return array<int, int>
     */
    private function weakTopicIds(int $userId): array
    {
        return DB::table('question_attempts')
            ->join('question_topic', 'question_topic.question_id', '=', 'question_attempts.question_id')
            ->where('question_attempts.user_id', $userId)
            ->whereNotNull('question_attempts.is_correct')
            ->groupBy('question_topic.topic_id')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByRaw('AVG(CASE WHEN question_attempts.is_correct = 1 THEN 1.0 ELSE 0.0 END)')
            ->limit(5)
            ->pluck('question_topic.topic_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
        ?array $eligible,
        array $difficulties,
        bool $canUsePremium,
        ?CreateSessionData $data = null,
        ?int $userId = null,
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
            $canUsePremium,
            $data,
            $userId,
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
        ?array $eligible,
        array $difficulties,
        bool $canUsePremium,
        ?CreateSessionData $data = null,
        ?int $userId = null,
    ): Collection {
        if ($limit <= 0) {
            return collect();
        }

        return $this->questionQuery(
            $topicIds,
            $exclude,
            $eligible,
            $difficulties,
            $canUsePremium,
            $data,
            $userId,
        )
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('id');
    }

    /**
     * @param  array<int, int>  $topicIds
     * @param  array<int, string>  $exclude
     * @param  array<int, string>|null  $eligible
     * @param  array<int, string>  $difficulties
     * @return Builder<Question>
     */
    private function questionQuery(
        array $topicIds,
        array $exclude,
        ?array $eligible,
        array $difficulties,
        bool $canUsePremium,
        ?CreateSessionData $data = null,
        ?int $userId = null,
    ): Builder {
        $query = Question::query()
            ->where('status', QuestionStatus::Published)
            ->when(! $canUsePremium, fn ($query) => $query->where('is_free', true))
            ->when(
                $topicIds !== [],
                fn ($query) => $query->whereHas(
                    'topics',
                    fn (Builder $topics) => $topics->whereIn('topics.id', $topicIds),
                ),
            )
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('id', $exclude))
            ->when($eligible !== null, fn ($query) => $query->whereIn('id', $eligible))
            ->when($difficulties !== [], fn ($query) => $query->whereIn('difficulty', $difficulties));

        if (! $data instanceof CreateSessionData) {
            return $query;
        }

        // Apply saved-only or specific folder filtering
        if ($data->folderId !== null && $userId !== null) {
            $itemQuestionIds = BookmarkFolderItem::query()
                ->where('folder_id', $data->folderId)
                ->pluck('question_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $query->whereIn('id', $itemQuestionIds);
        } elseif ($data->savedOnly && $userId !== null) {
            $query->whereIn('id', Bookmark::bookmarkSubquery($userId));
        }

        $examKeys = $data->examKey === null
            ? []
            : [TargetExams::filterTag($data->examKey)];

        $query = $this->whereAssignedToAny($query, QuestionScopeType::Exam, $examKeys);
        $query = $this->whereAssignedToAny($query, QuestionScopeType::Article, $data->articles);

        return $this->whereAssignedToAny($query, QuestionScopeType::Symptom, $data->symptoms);
    }

    /**
     * Values inside one facet are OR-ed. Calling this once per facet makes the
     * selected exam/article/symptom groups combine with AND semantics.
     *
     * @param  Builder<Question>  $query
     * @param  array<int, string>  $keys
     * @return Builder<Question>
     */
    private function whereAssignedToAny(
        Builder $query,
        QuestionScopeType $type,
        array $keys,
    ): Builder {
        if ($keys === []) {
            return $query;
        }

        return $query->whereHas(
            'scopes',
            fn (Builder $scope): Builder => $scope
                ->where('scope_type', $type->value)
                ->whereIn('scope_key', array_values(array_unique($keys))),
        );
    }

    /**
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
     * @param  array<int, string>  $statuses
     * @return array<int, string>
     */
    private function eligibleQuestionIds(
        int $userId,
        array $statuses,
        string $mode,
        bool $canUsePremium,
    ): array {
        $statuses = collect($statuses)
            ->map(static fn (string $status): string => match (strtolower($status)) {
                'wrong' => 'incorrect',
                'skipped' => 'omitted',
                'saved' => 'marked',
                'unseen' => 'unanswered',
                default => strtolower($status),
            })
            ->unique()
            ->values()
            ->all();

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

        $directStatuses = [];
        if (in_array('marked', $statuses, true)) {
            $eligible = $eligible->concat(Bookmark::questionIdsForUser($userId));
        }
        if (in_array('omitted', $statuses, true)) {
            $directStatuses[] = UserQuestionStatus::Omitted;
        }

        if ($directStatuses !== []) {
            $eligible = $eligible->concat($this->statusQuestionIds($userId, $directStatuses));
        }

        if (in_array('unanswered', $statuses, true)) {
            $answered = $attempts->pluck('question_id')->unique()->all();
            $unanswered = Question::query()
                ->where('status', QuestionStatus::Published)
                ->when(! $canUsePremium, fn ($query) => $query->where('is_free', true))
                ->when($answered !== [], fn ($query) => $query->whereNotIn('id', $answered))
                ->pluck('id');
            $eligible = $eligible
                ->concat($unanswered)
                ->concat($this->statusQuestionIds($userId, [UserQuestionStatus::Unseen]));
        }

        return $eligible->unique()->values()->all();
    }

    /**
     * @param  array<int, UserQuestionStatus>  $statuses
     * @return array<int, string>
     */
    private function statusQuestionIds(int $userId, array $statuses): array
    {
        return UserQuestionStatusModel::query()
            ->where('user_id', $userId)
            ->whereIn('status', $statuses)
            ->pluck('question_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function parseDifficulties(array $values): array
    {
        return collect($values)
            ->map(static fn (string $value): ?string => Difficulty::tryFrom($value)?->value)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
