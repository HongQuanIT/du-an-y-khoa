<?php

declare(strict_types=1);

namespace Modules\Search\Services;

use App\Models\User;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;
use Modules\Search\Contracts\ScopedSearchProvider;
use Modules\Search\Data\ScopedSearchResult;
use Modules\Search\Data\SearchQueryData;

/** Resolves a context provider without coupling controllers to domain models. */
final class ScopedSearchManager
{
    public function __construct(private readonly Container $container) {}

    public function search(SearchQueryData $data, User $user): ScopedSearchResult
    {
        return $this->provider($data->scope)->search($data, $user);
    }

    private function provider(string $scope): ScopedSearchProvider
    {
        $provider = config("search.scopes.{$scope}");

        if (! is_string($provider) || $provider === '') {
            throw new InvalidArgumentException("Unsupported search scope [{$scope}].");
        }

        $instance = $this->container->make($provider);

        if (! $instance instanceof ScopedSearchProvider) {
            throw new LogicException("Search provider [{$provider}] must implement ScopedSearchProvider.");
        }

        return $instance;
    }
}
