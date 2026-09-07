<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Services;

use Illuminate\Support\Collection;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Services\SessionQuestionSelector;
use Modules\StudyPlan\Models\StudyPlan;

final class StudyPlanQuestionPool
{
    public function __construct(private readonly SessionQuestionSelector $selector) {}

    /** @return list<string> */
    public function questionIds(StudyPlan $plan): array
    {
        $filters = $plan->scopeFilters();
        $user = $plan->user()->firstOrFail();

        return $this->selector->questionPoolIds(
            $user,
            $this->sessionData($filters, 1, null, $plan->exam_key),
        );
    }

    /**
     * Snapshot the properties used when a plan allocation is created.
     *
     * @return Collection<int, array{
     *     question_id: string,
     *     source_status: string,
     *     status_priority: int,
     *     high_yield_score: float
     * }>
     */
    public function candidates(StudyPlan $plan): Collection
    {
        $ids = $this->questionIds($plan);
        if ($ids === []) {
            return collect();
        }

        $attempts = QuestionAttempt::query()
            ->where('user_id', $plan->user_id)
            ->whereIn('question_id', $ids)
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->get(['question_id', 'is_correct', 'used_hint'])
            ->unique('question_id')
            ->keyBy(fn (QuestionAttempt $attempt): string => (string) $attempt->question_id);

        return Question::query()
            ->whereIn('id', $ids)
            ->with('tags:id,slug')
            ->get(['id', 'exam_flag'])
            ->map(function (Question $question) use ($attempts): array {
                $attempt = $attempts->get((string) $question->getKey());
                $sourceStatus = match (true) {
                    $attempt === null => 'unanswered',
                    $attempt->is_correct === false => 'incorrect',
                    $attempt->is_correct === true && $attempt->used_hint => 'correct_with_hints',
                    $attempt->is_correct === true => 'correct',
                    default => 'unanswered',
                };

                return [
                    'question_id' => (string) $question->getKey(),
                    'source_status' => $sourceStatus,
                    'status_priority' => match ($sourceStatus) {
                        'incorrect' => 4,
                        'unanswered' => 3,
                        'correct_with_hints' => 2,
                        'correct' => 1,
                        default => 0,
                    },
                    'high_yield_score' => match (true) {
                        $question->tags->contains('slug', 'high-yield') => 100.0,
                        $question->exam_flag => 80.0,
                        default => 0.0,
                    },
                ];
            })
            ->values();
    }

    /**
     * Select the next adaptive batch from the live bank state.
     * Incorrect answers are exhausted first; unseen questions fill the rest.
     * Questions whose latest answer is correct are intentionally excluded.
     *
     * @return list<string>
     */
    public function nextQuestionIds(StudyPlan $plan, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        return $this->selector->forAdaptiveSession(
            $plan->user()->firstOrFail(),
            $this->sessionData($plan->scopeFilters(), $limit, null, $plan->exam_key),
            true,
        );
    }

    /** @return list<string> */
    public function incorrectQuestionIds(StudyPlan $plan, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        return $this->selector->forSession(
            $plan->user()->firstOrFail(),
            $this->sessionData($plan->scopeFilters(), $limit, ['incorrect'], $plan->exam_key),
        );
    }

    /** @param array<string, mixed> $filters */
    private function sessionData(
        array $filters,
        int $count,
        ?array $questionStatuses = null,
        ?string $examKey = null,
    ): CreateSessionData {
        return new CreateSessionData(
            mode: SessionMode::Study,
            source: SessionSource::Custom,
            count: max(1, $count),
            blueprintId: isset($filters['blueprint_id']) ? (int) $filters['blueprint_id'] : null,
            blueprintSectionId: isset($filters['blueprint_section_id']) ? (int) $filters['blueprint_section_id'] : null,
            coreClinicalTopicIds: array_map('intval', $filters['core_clinical_topic_ids'] ?? []),
            medicalTaxonomyNodeIds: array_map('intval', $filters['medical_taxonomy_node_ids'] ?? []),
            systemIds: array_map('intval', $filters['system_ids'] ?? []),
            disciplineIds: array_map('intval', $filters['discipline_ids'] ?? []),
            tagIds: array_map('intval', $filters['tag_ids'] ?? []),
            difficulties: array_values($filters['difficulties'] ?? []),
            questionStatuses: array_values($questionStatuses ?? $filters['question_statuses'] ?? []),
            questionStatusMode: $questionStatuses === null
                ? (string) ($filters['question_status_mode'] ?? 'latest')
                : 'latest',
            savedOnly: (bool) ($filters['saved_only'] ?? false),
            examKey: $examKey ?: (($filters['exam_tags'][0] ?? null) ?: null),
            articles: array_values($filters['articles'] ?? []),
            symptoms: array_values($filters['symptoms'] ?? []),
        );
    }
}
