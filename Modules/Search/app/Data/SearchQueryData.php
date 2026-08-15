<?php

declare(strict_types=1);

namespace Modules\Search\Data;

final class SearchQueryData
{
    /**
     * @param  array{difficulty?: string, topic_id?: int, is_free?: bool}  $filters
     */
    public function __construct(
        public readonly string $scope,
        public readonly string $query,
        public readonly array $filters = [],
        public readonly int $page = 1,
        public readonly int $perPage = 20,
    ) {}
}
