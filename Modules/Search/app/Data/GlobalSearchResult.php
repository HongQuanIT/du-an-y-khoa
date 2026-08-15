<?php

declare(strict_types=1);

namespace Modules\Search\Data;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GlobalSearchResult
{
    /**
     * @param  LengthAwarePaginator<int, array<string, mixed>>  $paginator
     * @param  array<string, array<int, array{value: mixed, count: int}>>  $facets
     */
    public function __construct(
        public readonly LengthAwarePaginator $paginator,
        public readonly array $facets,
        public readonly bool $degraded,
        public readonly string $engine,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function items(): array
    {
        return array_values($this->paginator->items());
    }

    /** @return array{page: int, per_page: int, total: int, total_pages: int} */
    public function pagination(): array
    {
        return [
            'page' => $this->paginator->currentPage(),
            'per_page' => $this->paginator->perPage(),
            'total' => $this->paginator->total(),
            'total_pages' => $this->paginator->lastPage(),
        ];
    }
}
