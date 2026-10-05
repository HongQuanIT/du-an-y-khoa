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

    public static function id(): ?string
    {
        return self::$runId;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function write(string $step, array $context = []): void
    {
        if (self::$runId === null) {
            self::begin();
        }

        self::writeWithTrace(self::$runId, $step, $context);
    }

    /**
     * Ghi thêm bước vào cùng lần chọn câu (vd. bảng chấm sau khi hoàn thành phiên).
     *
     * @param  array<string, mixed>  $context
     */
    public static function writeWithTrace(string $traceId, string $step, array $context = []): void
    {
        $context['trace_id'] = $traceId;

        Log::channel('adaptive')->debug('[adaptive] '.$step, $context);
    }
}
