<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Data;

use App\Support\Data\DataTransferObject;

/**
 * Validated input for listing questions.
 */
final class ListQuestionsData extends DataTransferObject
{
    public function __construct(
        public readonly ?string $query = null,
        public readonly ?string $difficulty = null,
        public readonly ?int $topicId = null,
        public readonly ?bool $freeOnly = null,
        public readonly int $perPage = 20,
    ) {}
}
