<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Data;

use App\Support\Data\DataTransferObject;

/**
 * Validated input for listing questions.
 */
final class ListQuestionsData extends DataTransferObject
{
    /**
     * @param  list<int>  $coreClinicalTopicIds
     * @param  list<int>  $medicalTaxonomyNodeIds
     * @param  list<int>  $tagIds
     */
    public function __construct(
        public readonly ?string $query = null,
        public readonly ?string $difficulty = null,
        public readonly ?int $blueprintId = null,
        public readonly ?int $blueprintSectionId = null,
        public readonly array $coreClinicalTopicIds = [],
        public readonly array $medicalTaxonomyNodeIds = [],
        public readonly array $tagIds = [],
        public readonly ?bool $freeOnly = null,
        public readonly int $perPage = 20,
    ) {}
}
