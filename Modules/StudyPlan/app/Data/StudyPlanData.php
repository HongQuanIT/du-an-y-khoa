<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Data;

use App\Support\Data\DataTransferObject;

/**
 * Validated wizard input for creating or editing a plan.
 *
 * @property-read array<int, int> $topicIds
 * @property-read array<int, int> $studyDays
 * @property-read array<int, string> $examTags
 * @property-read array<int, string> $articles
 * @property-read array<int, string> $symptoms
 * @property-read array<int, string> $difficulties
 * @property-read array<int, string> $questionStatuses
 */
final class StudyPlanData extends DataTransferObject
{
    /**
     * @param  array<int, int>  $topicIds
     * @param  array<int, int>  $studyDays  ISO weekdays (1 = Monday)
     * @param  array<int, string>  $examTags
     * @param  array<int, string>  $articles
     * @param  array<int, string>  $symptoms
     * @param  array<int, string>  $difficulties
     * @param  array<int, string>  $questionStatuses
     */
    public function __construct(
        public readonly string $name,
        public readonly string $examKey,
        public readonly string $examTargetDate,
        public readonly int $dailyGoalQuestions,
        public readonly array $topicIds = [],
        public readonly array $studyDays = [1, 2, 3, 4, 5],
        public readonly string $strategy = 'fixed',
        public readonly array $examTags = [],
        public readonly array $articles = [],
        public readonly array $symptoms = [],
        public readonly bool $savedOnly = false,
        public readonly array $difficulties = [],
        public readonly array $questionStatuses = [],
        public readonly string $questionStatusMode = 'latest',
    ) {}

    /** Rough time budget shown in the wizard preview (~2.25 min per question). */
    public function dailyGoalMinutes(): int
    {
        return max(5, (int) round($this->dailyGoalQuestions * 2.25));
    }

    /**
     * Persistable Amboss-style filter bag for `study_plans.topic_scope`.
     *
     * @return array<string, mixed>
     */
    public function topicScopePayload(): array
    {
        return [
            'topic_ids' => array_values($this->topicIds),
            'exam_tags' => array_values($this->examTags),
            'articles' => array_values($this->articles),
            'symptoms' => array_values($this->symptoms),
            'saved_only' => $this->savedOnly,
            'difficulties' => array_values($this->difficulties),
            'difficulty' => count($this->difficulties) === 1 ? $this->difficulties[0] : null,
            'question_statuses' => array_values($this->questionStatuses),
            'question_status_mode' => $this->questionStatusMode,
        ];
    }
}
