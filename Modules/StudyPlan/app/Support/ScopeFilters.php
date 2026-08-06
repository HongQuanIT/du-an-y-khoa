<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Support;

use App\Support\ScopeFilters as SharedScopeFilters;

/** Compatibility facade for shared learning-scope catalogs. */
final class ScopeFilters
{
    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function examTags(): array
    {
        return SharedScopeFilters::examTags();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function articles(): array
    {
        return SharedScopeFilters::articles();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function symptoms(): array
    {
        return SharedScopeFilters::symptoms();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function difficulties(): array
    {
        return SharedScopeFilters::difficulties();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function questionStatuses(): array
    {
        return SharedScopeFilters::questionStatuses();
    }
}
