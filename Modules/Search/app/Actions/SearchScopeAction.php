<?php

declare(strict_types=1);

namespace Modules\Search\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Search\Data\ScopedSearchResult;
use Modules\Search\Data\SearchQueryData;
use Modules\Search\Services\ScopedSearchManager;

final class SearchScopeAction
{
    use AsAction;

    public function __construct(private readonly ScopedSearchManager $search) {}

    public function handle(SearchQueryData $data, User $user): ScopedSearchResult
    {
        return $this->search->search($data, $user);
    }
}
