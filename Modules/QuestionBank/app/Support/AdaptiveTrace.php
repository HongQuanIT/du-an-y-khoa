<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * One id per adaptive selection, so the CEO briefing can group log lines
 * that belong to the same practice session.
 */
final class AdaptiveTrace
{
    private static ?string $runId = null;

    public static function begin(): void
    {
        self::$runId = (string) Str::ulid();
    }

    public static function finish(): void
    {
        self::$runId = null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function write(string $step, array $context = []): void
    {
        if (self::$runId === null) {
            self::begin();
        }

        $context['trace_id'] = self::$runId;

        Log::channel('adaptive')->debug('[adaptive] '.$step, $context);
    }
}
