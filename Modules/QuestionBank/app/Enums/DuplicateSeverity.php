<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

/**
 * Mức độ trùng lặp nội dung câu hỏi (theo % similarity).
 */
enum DuplicateSeverity: string
{
    case Exact = 'exact';
    case VeryHigh = 'very_high';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /** Ngưỡng tối thiểu để hiển thị / lưu pair. */
    public const DISPLAY_THRESHOLD = 30.0;

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Trùng khớp 100%',
            self::VeryHigh => 'Rất cao (≥90%)',
            self::High => 'Cao (≥75%)',
            self::Medium => 'Trung bình (≥60%)',
            self::Low => 'Thấp (≥30%)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Exact => 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200',
            self::VeryHigh => 'bg-orange-100 text-orange-900 dark:bg-orange-950/50 dark:text-orange-200',
            self::High => 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
            self::Medium => 'bg-yellow-100 text-yellow-900 dark:bg-yellow-950/40 dark:text-yellow-200',
            self::Low => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
        };
    }

    public static function fromPercent(float $percent): ?self
    {
        return match (true) {
            $percent >= 100.0 => self::Exact,
            $percent >= 90.0 => self::VeryHigh,
            $percent >= 75.0 => self::High,
            $percent >= 60.0 => self::Medium,
            $percent >= self::DISPLAY_THRESHOLD => self::Low,
            default => null,
        };
    }

    public function minPercent(): float
    {
        return match ($this) {
            self::Exact => 100.0,
            self::VeryHigh => 90.0,
            self::High => 75.0,
            self::Medium => 60.0,
            self::Low => self::DISPLAY_THRESHOLD,
        };
    }
}
