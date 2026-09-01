<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Admin\Enums\ReportScheduleFrequency;
use Modules\Admin\Support\AdminReportCatalog;

/**
 * @property int $id
 * @property int $created_by
 * @property string $category_slug
 * @property string $report_slug
 * @property string $range_key
 * @property ReportScheduleFrequency $frequency
 * @property int|null $weekday
 * @property int|null $day_of_month
 * @property string $send_time
 * @property list<string> $recipients
 * @property bool $is_active
 * @property bool $send_email
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_run_at
 */
class ReportSchedule extends Model
{
    protected $fillable = [
        'created_by',
        'category_slug',
        'report_slug',
        'range_key',
        'frequency',
        'weekday',
        'day_of_month',
        'send_time',
        'recipients',
        'is_active',
        'send_email',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => ReportScheduleFrequency::class,
            'weekday' => 'integer',
            'day_of_month' => 'integer',
            'recipients' => 'array',
            'is_active' => 'boolean',
            'send_email' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<self> $query */
    public function scopeDue(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $at);
    }

    public function reportTitle(): string
    {
        $match = AdminReportCatalog::findReport($this->category_slug, $this->report_slug);

        return $match['report']['title'] ?? "{$this->category_slug}/{$this->report_slug}";
    }

    public function categoryTitle(): string
    {
        $category = AdminReportCatalog::findCategory($this->category_slug);

        return $category['title'] ?? $this->category_slug;
    }

    public function frequencySummary(): string
    {
        $time = substr((string) $this->send_time, 0, 5);

        return match ($this->frequency) {
            ReportScheduleFrequency::Daily => "Hàng ngày lúc {$time}",
            ReportScheduleFrequency::Weekly => sprintf(
                'Hàng tuần · %s lúc %s',
                $this->weekdayLabel(),
                $time,
            ),
            ReportScheduleFrequency::Monthly => sprintf(
                'Hàng tháng · ngày %d lúc %s',
                (int) $this->day_of_month,
                $time,
            ),
        };
    }

    public function weekdayLabel(): string
    {
        return match ((int) $this->weekday) {
            1 => 'Thứ Hai',
            2 => 'Thứ Ba',
            3 => 'Thứ Tư',
            4 => 'Thứ Năm',
            5 => 'Thứ Sáu',
            6 => 'Thứ Bảy',
            7 => 'Chủ Nhật',
            default => '—',
        };
    }

    public function computeNextRunAt(?Carbon $from = null): Carbon
    {
        $from = ($from ?? now())->copy();
        $time = $this->parseSendTime();

        return match ($this->frequency) {
            ReportScheduleFrequency::Daily => $this->nextDaily($from, $time),
            ReportScheduleFrequency::Weekly => $this->nextWeekly($from, $time),
            ReportScheduleFrequency::Monthly => $this->nextMonthly($from, $time),
        };
    }

    public function refreshNextRunAt(?Carbon $from = null): void
    {
        $this->forceFill([
            'next_run_at' => $this->computeNextRunAt($from),
        ])->save();
    }

    /** @return array{0: int, 1: int} hour, minute */
    private function parseSendTime(): array
    {
        $parts = explode(':', (string) $this->send_time);

        return [(int) ($parts[0] ?? 8), (int) ($parts[1] ?? 0)];
    }

    /** @param array{0: int, 1: int} $time */
    private function nextDaily(Carbon $from, array $time): Carbon
    {
        $candidate = $from->copy()->setTime($time[0], $time[1], 0);
        if ($candidate->lte($from)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    /** @param array{0: int, 1: int} $time */
    private function nextWeekly(Carbon $from, array $time): Carbon
    {
        $targetDow = max(1, min(7, (int) $this->weekday)); // ISO: 1=Mon … 7=Sun
        $candidate = $from->copy()->setTime($time[0], $time[1], 0);

        while ((int) $candidate->dayOfWeekIso !== $targetDow || $candidate->lte($from)) {
            $candidate->addDay()->setTime($time[0], $time[1], 0);
        }

        return $candidate;
    }

    /** @param array{0: int, 1: int} $time */
    private function nextMonthly(Carbon $from, array $time): Carbon
    {
        $day = max(1, min(28, (int) $this->day_of_month));
        $candidate = $from->copy()->day($day)->setTime($time[0], $time[1], 0);

        if ($candidate->lte($from)) {
            $candidate->addMonthNoOverflow()->day($day)->setTime($time[0], $time[1], 0);
        }

        return $candidate;
    }
}
