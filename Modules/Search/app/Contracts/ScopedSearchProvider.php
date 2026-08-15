<?php

declare(strict_types=1);

namespace Modules\Search\Contracts;

use App\Models\User;
use Modules\Search\Data\ScopedSearchResult;
use Modules\Search\Data\SearchQueryData;

interface ScopedSearchProvider
{
    public function search(SearchQueryData $data, User $user): ScopedSearchResult;
}
