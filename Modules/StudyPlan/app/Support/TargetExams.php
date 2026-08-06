<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Support;

use App\Support\TargetExams as SharedTargetExams;
use Illuminate\Support\Carbon;

/** Compatibility facade for the shared exam catalog. */
final class TargetExams
{
    /**
     * @return array<string, array{title: string, icon: string, hint: string, legacy?: bool}>
     */
    public static function all(): array
    {
        return SharedTargetExams::all();
    }

    /**
     * Exams shown in the create/edit wizard (excludes legacy aliases).
     *
     * @return array<string, array{title: string, icon: string, hint: string}>
     */
    public static function selectable(): array
    {
        return SharedTargetExams::selectable();
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return SharedTargetExams::keys();
    }

    public static function title(?string $key): string
    {
        return SharedTargetExams::title($key);
    }

    public static function planName(string $key, Carbon $targetDate): string
    {
        return SharedTargetExams::planName($key, $targetDate);
    }

    /** Map a wizard exam key onto the Amboss-style filter tag used in topic_scope. */
    public static function filterTag(string $key): string
    {
        return SharedTargetExams::filterTag($key);
    }
}
