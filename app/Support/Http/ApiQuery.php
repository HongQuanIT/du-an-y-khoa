<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Applies the conventional query params (filter/sort/include/pagination)
 * to an Eloquent builder with strict whitelisting.
 * See srs/00-nen-tang/05-api-conventions.md §5.
 */
final class ApiQuery
{
    private const MAX_PER_PAGE = 100;

    private const DEFAULT_PER_PAGE = 20;

    /**
     * @param  list<string>  $allowedFilters
     * @param  list<string>  $allowedSorts
     * @param  list<string>  $allowedIncludes
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function apply(
        Builder $query,
        Request $request,
        array $allowedFilters = [],
        array $allowedSorts = [],
        array $allowedIncludes = [],
    ): Builder {
        self::applyFilters($query, $request, $allowedFilters);
        self::applySorts($query, $request, $allowedSorts);
        self::applyIncludes($query, $request, $allowedIncludes);

        return $query;
    }

    public static function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $allowed
     */
    private static function applyFilters(Builder $query, Request $request, array $allowed): void
    {
        $filters = $request->array('filter');

        foreach ($filters as $field => $value) {
            if (! in_array($field, $allowed, true)) {
                continue;
            }

            is_array($value)
                ? $query->whereIn($field, $value)
                : $query->where($field, $value);
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $allowed
     */
    private static function applySorts(Builder $query, Request $request, array $allowed): void
    {
        $sort = (string) $request->query('sort', '');

        if ($sort === '') {
            return;
        }

        foreach (explode(',', $sort) as $column) {
            $direction = str_starts_with($column, '-') ? 'desc' : 'asc';
            $column = ltrim($column, '-');

            if (in_array($column, $allowed, true)) {
                $query->orderBy($column, $direction);
            }
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $allowed
     */
    private static function applyIncludes(Builder $query, Request $request, array $allowed): void
    {
        $include = (string) $request->query('include', '');

        if ($include === '') {
            return;
        }

        $relations = array_values(array_filter(
            explode(',', $include),
            static fn (string $relation) => in_array($relation, $allowed, true),
        ));

        if ($relations !== []) {
            $query->with($relations);
        }
    }
}
