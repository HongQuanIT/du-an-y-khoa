<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Data;

use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;

/**
 * Input bag for creating a custom / exam / weak-topics Q-Bank session.
 *
 * @property-read array<int, int> $topicIds
 * @property-read array<int, string> $difficulties
 * @property-read array<int, string> $questionStatuses
 * @property-read array<int, string> $articles
 * @property-read array<int, string> $symptoms
 */
final class CreateSessionData
{
    /**
     * @param  array<int, int>  $topicIds
     * @param  array<int, string>  $difficulties
     * @param  array<int, string>  $questionStatuses
     * @param  array<int, string>  $articles
     * @param  array<int, string>  $symptoms
     */
    public function __construct(
        public readonly SessionMode $mode = SessionMode::Study,
        public readonly SessionSource $source = SessionSource::Custom,
        public readonly int $count = 10,
        public readonly array $topicIds = [],
        public readonly array $difficulties = [],
        public readonly array $questionStatuses = [],
        public readonly string $questionStatusMode = 'latest',
        public readonly bool $savedOnly = false,
        public readonly ?string $examKey = null,
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
            'topic_ids' => array_values($this->topicIds),
            'difficulties' => array_values($this->difficulties),
            'difficulty' => count($this->difficulties) === 1 ? $this->difficulties[0] : null,
            'question_statuses' => array_values($this->questionStatuses),
            'question_status_mode' => $this->questionStatusMode,
            'saved_only' => $this->savedOnly,
            'exam_key' => $this->examKey,
            'articles' => array_values($this->articles),
            'symptoms' => array_values($this->symptoms),
            'count' => $this->count,
        ];
    }
}
