<?php

declare(strict_types=1);

namespace Modules\Notification\Support;

final class NotificationCatalog
{
    /** @return array<string, mixed>|null */
    public static function definition(string $type): ?array
    {
        $types = config('notification.types', []);

        return is_array($types[$type] ?? null) ? $types[$type] : null;
    }

    public static function category(string $type): string
    {
        return (string) (self::definition($type)['category'] ?? 'system');
    }

    public static function icon(string $type): string
    {
        return (string) (self::definition($type)['icon'] ?? 'notifications');
    }

    public static function preferenceKey(string $type): ?string
    {
        $key = self::definition($type)['preference_key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    public static function bypassesPreferences(string $type): bool
    {
        return (bool) (self::definition($type)['bypass_prefs'] ?? false);
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultPreferences(): array
    {
        $defaults = config('notification.default_prefs', []);

        return is_array($defaults) ? $defaults : [];
    }

    /**
     * @return list<string>
     */
    public static function filterCategories(): array
    {
        return ['reminder', 'result', 'classroom', 'system', 'billing', 'support'];
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'reminder' => 'Nhắc học',
            'result' => 'Kết quả',
            'classroom' => 'Lớp / Live',
            'system' => 'Hệ thống',
            'billing' => 'Thanh toán',
            'support' => 'Hỗ trợ',
            default => 'Khác',
        };
    }
}
