<?php

declare(strict_types=1);

namespace Modules\Search\Data;

final class GlobalSearchQueryData
{
    public function __construct(
        public readonly string $query,
        public readonly int $page = 1,
        public readonly int $perPage = 20,
        public readonly ?string $type = null,
    ) {}
}
