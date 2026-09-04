<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\UserActivitySession;
use Illuminate\Support\Carbon;

/**
 * Turns page presence into CSKH-friendly Vietnamese copy.
 */
final class UserActivityPresenter
{
    /**
     * @return array{
     *   when: string,
     *   when_exact: string,
     *   summary: string,
     *   detail: string,
     *   portal_label: string,
     *   place_label: string,
     *   device_label: string,
     *   ip: string
     * }
     */
    public static function present(UserActivitySession $activity): array
    {
        $portal = self::portalLabel((string) $activity->portal);
        $place = ActivityArea::label((string) $activity->area);
        $when = self::relativeWhen($activity->last_seen_at);
        $device = self::deviceLabel($activity);

        $detailParts = array_values(array_filter([
            "Cổng {$portal}",
            $device !== '' ? $device : null,
            $activity->ip ? 'IP '.$activity->ip : null,
        ]));

        return [
            'when' => $when,
            'when_exact' => $activity->last_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—',
            'summary' => "Đã mở {$place}",
            'detail' => implode(' · ', $detailParts),
            'portal_label' => $portal,
            'place_label' => $place,
            'device_label' => $device !== '' ? $device : 'Thiết bị không xác định',
            'ip' => $activity->ip ?: '—',
        ];
    }

    public static function portalLabel(string $portal): string
    {
        return match ($portal) {
            'admin' => 'quản trị',
            'teach' => 'giảng viên',
            'partner' => 'cộng tác viên',
            default => 'học viên',
        };
    }

    /** @deprecated Use ActivityArea::label() */
    public static function placeLabel(string $area): string
    {
        return ActivityArea::label($area);
    }

    public static function relativeWhen(?Carbon $at): string
    {
        if ($at === null) {
            return 'Không rõ thời điểm';
        }

        $seconds = (int) max(0, $at->diffInSeconds(now()));
        if ($seconds < 60) {
            return 'Vừa xong';
        }
        if ($seconds < 3600) {
            $m = intdiv($seconds, 60);

            return $m === 1 ? '1 phút trước' : "{$m} phút trước";
        }
        if ($seconds < 86400) {
            $h = intdiv($seconds, 3600);

            return $h === 1 ? '1 giờ trước' : "{$h} giờ trước";
        }
        if ($seconds < 86400 * 7) {
            $d = intdiv($seconds, 86400);

            return $d === 1 ? 'Hôm qua' : "{$d} ngày trước";
        }

        return $at->timezone(config('app.timezone'))->format('d/m/Y H:i');
    }

    private static function deviceLabel(UserActivitySession $activity): string
    {
        $parts = array_values(array_filter([
            $activity->browser,
            $activity->operating_system ? 'trên '.$activity->operating_system : null,
            $activity->device_name && ! in_array($activity->device_name, [(string) $activity->operating_system, (string) $activity->browser], true)
                ? '('.$activity->device_name.')'
                : null,
        ]));

        return implode(' ', $parts);
    }
}
