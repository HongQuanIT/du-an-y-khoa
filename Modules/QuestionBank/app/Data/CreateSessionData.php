<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Data;

use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;

/**
 * Input bag for creating a custom / exam / weak-topics Q-Bank session.
 *
 * @property-read array<int, int> $coreClinicalTopicIds
 * @property-read array<int, int> $organSystemIds
 * @property-read array<int, int> $subjectIds
 * @property-read array<int, int> $lessonIds
 * @property-read array<int, int> $tagIds
 * @property-read array<int, string> $difficulties
 * @property-read array<int, string> $questionStatuses
 * @property-read array<int, string> $articles
 * @property-read array<int, string> $symptoms
 */
final class CreateSessionData
{
    /**
     * @param  array<int, int>  $coreClinicalTopicIds
     * @param  array<int, int>  $organSystemIds
     * @param  array<int, int>  $subjectIds
     * @param  array<int, int>  $lessonIds
     * @param  array<int, int>  $tagIds
     * @param  array<int, string>  $difficulties
     * @param  array<int, string>  $questionStatuses
     * @param  array<int, string>  $articles
     * @param  array<int, string>  $symptoms
     */
    public function __construct(
        public readonly SessionMode $mode = SessionMode::Study,
        public readonly SessionSource $source = SessionSource::Custom,
        public readonly int $count = 10,
        public readonly ?int $blueprintId = null,
        public readonly ?int $blueprintSectionId = null,
        public readonly array $coreClinicalTopicIds = [],
        public readonly array $organSystemIds = [],
        public readonly array $subjectIds = [],
        public readonly array $lessonIds = [],
        public readonly array $tagIds = [],
        public readonly array $difficulties = [],
        public readonly array $questionStatuses = [],
        public readonly string $questionStatusMode = 'latest',
        public readonly bool $savedOnly = false,
        public readonly ?int $folderId = null,
        public readonly ?string $examKey = null,
        public readonly ?int $examId = null,
        public readonly array $articles = [],
        public readonly array $symptoms = [],
    ) {}

    /**
     * Persistable filter snapshot stored on the session.
     *
     * @return array<string, mixed>
     */
    public function filtersPayload(): array
    {
        return [
            'blueprint_id' => $this->blueprintId,
            'blueprint_section_id' => $this->blueprintSectionId,
            'core_clinical_topic_ids' => array_values($this->coreClinicalTopicIds),
            'organ_system_ids' => array_values($this->organSystemIds),
            'subject_ids' => array_values($this->subjectIds),
            'lesson_ids' => array_values($this->lessonIds),
            'tag_ids' => array_values($this->tagIds),
            'difficulties' => array_values($this->difficulties),
            'difficulty' => count($this->difficulties) === 1 ? $this->difficulties[0] : null,
            'question_statuses' => array_values($this->questionStatuses),
            'question_status_mode' => $this->questionStatusMode,
            'saved_only' => $this->savedOnly,
            'folder_id' => $this->folderId,
            'exam_key' => $this->examKey,
            'exam_id' => $this->examId,
            'articles' => array_values($this->articles),
            'symptoms' => array_values($this->symptoms),
            'count' => $this->count,
        ];
    }
}
