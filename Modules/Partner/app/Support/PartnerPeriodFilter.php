<?php

declare(strict_types=1);

namespace Modules\Partner\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Reporting window + list filters for admin partner index.
 *
 * @phpstan-type PeriodArray array{
 *     preset: string,
 *     from: Carbon,
 *     to: Carbon,
 *     label: string,
 *     can_go_next: bool,
 * }
 */
final class PartnerPeriodFilter
{
    public const PRESET_THIS_MONTH = 'this_month';

    public const PRESET_LAST_MONTH = 'last_month';

    public const PRESET_THIS_WEEK = 'this_week';

    public const PRESET_LAST_WEEK = 'last_week';

    public const PRESET_CUSTOM = 'custom';

    public const SORT_COMMISSION = 'commission';

    public const SORT_GROSS = 'gross';

    public const SORT_REFERRALS = 'referrals';

    public const SORT_NAME = 'name';

    /**
     * @return list<string>
     */
    public static function presets(): array
    {
        return [
            self::PRESET_THIS_MONTH,
            self::PRESET_LAST_MONTH,
            self::PRESET_THIS_WEEK,
            self::PRESET_LAST_WEEK,
            self::PRESET_CUSTOM,
        ];
    }

    public static function presetLabel(string $preset): string
    {
        return match ($preset) {
            self::PRESET_THIS_MONTH => 'Tháng này',
            self::PRESET_LAST_MONTH => 'Tháng trước',
            self::PRESET_THIS_WEEK => 'Tuần này',
            self::PRESET_LAST_WEEK => 'Tuần trước',
            self::PRESET_CUSTOM => 'Tuỳ chọn',
            default => 'Tháng này',
        };
    }

    /**
     * @return PeriodArray
     */
    public static function fromRequest(Request $request): array
    {
        $preset = (string) $request->query('preset', self::PRESET_THIS_MONTH);
        if (! in_array($preset, self::presets(), true)) {
            $preset = self::PRESET_THIS_MONTH;
        }

        $now = Carbon::now();

        if ($preset === self::PRESET_CUSTOM) {
            $fromRaw = $request->query('from');
            $toRaw = $request->query('to');

            $from = is_string($fromRaw) && $fromRaw !== ''
                ? Carbon::parse($fromRaw)->startOfDay()
                : $now->copy()->startOfMonth();
            $to = is_string($toRaw) && $toRaw !== ''
                ? Carbon::parse($toRaw)->endOfDay()
                : $now->copy()->endOfDay();

            if ($to->lt($from)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            return [
                'preset' => self::PRESET_CUSTOM,
                'from' => $from,
                'to' => $to,
                'label' => $from->format('d/m/Y').' – '.$to->format('d/m/Y'),
                'can_go_next' => false,
            ];
        }

        [$from, $to] = match ($preset) {
            self::PRESET_LAST_MONTH => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            self::PRESET_THIS_WEEK => [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            self::PRESET_LAST_WEEK => [
                $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
                $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY),
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
        };

        $label = match ($preset) {
            self::PRESET_LAST_MONTH => 'Tháng '.$from->format('m/Y'),
            self::PRESET_THIS_WEEK, self::PRESET_LAST_WEEK => 'Tuần '.$from->format('d/m').' – '.$to->format('d/m/Y'),
            default => 'Tháng '.$from->format('m/Y'),
        };

        return [
            'preset' => $preset,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'can_go_next' => $to->lt($now),
        ];
    }

    /**
     * @return array{status: string, q: string, sort: string, dir: string}
     */
    public static function listFilters(Request $request): array
    {
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', 'active', 'suspended'], true)) {
            $status = 'all';
        }

        $sort = (string) $request->query('sort', self::SORT_COMMISSION);
        if (! in_array($sort, [self::SORT_COMMISSION, self::SORT_GROSS, self::SORT_REFERRALS, self::SORT_NAME], true)) {
            $sort = self::SORT_COMMISSION;
        }

        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        return [
            'status' => $status,
            'q' => trim((string) $request->query('q', '')),
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    /**
     * Shift preset to adjacent calendar week/month (not used for custom).
     */
    public static function adjacentPreset(string $preset, string $direction): ?string
    {
        $map = [
            self::PRESET_THIS_MONTH => ['prev' => self::PRESET_LAST_MONTH, 'next' => null],
            self::PRESET_LAST_MONTH => ['prev' => null, 'next' => self::PRESET_THIS_MONTH],
            self::PRESET_THIS_WEEK => ['prev' => self::PRESET_LAST_WEEK, 'next' => null],
            self::PRESET_LAST_WEEK => ['prev' => null, 'next' => self::PRESET_THIS_WEEK],
        ];

        if (! isset($map[$preset])) {
            return null;
        }

        return $map[$preset][$direction === 'next' ? 'next' : 'prev'] ?? null;
    }
}
