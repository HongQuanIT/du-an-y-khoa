<?php

declare(strict_types=1);

namespace App\Support\Audit;

final class UserAgentParser
{
    /**
     * @return array{device_type: ?string, device_name: ?string, operating_system: ?string, browser: ?string}
     */
    public static function parse(?string $userAgent): array
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return self::emptyResult();
        }

        [$deviceType, $deviceName] = self::device($userAgent);

        return [
            'device_type' => $deviceType,
            'device_name' => $deviceName,
            'operating_system' => self::operatingSystem($userAgent),
            'browser' => self::browser($userAgent),
        ];
    }

    /** @return array{0: string, 1: string} */
    private static function device(string $userAgent): array
    {
        if (preg_match('/bot|crawler|spider|slurp|bingpreview/i', $userAgent) === 1) {
            return ['bot', 'Bot'];
        }

        if (str_contains($userAgent, 'iPad')) {
            return ['tablet', 'iPad'];
        }

        if (str_contains($userAgent, 'iPhone')) {
            return ['mobile', 'iPhone'];
        }

        if (str_contains($userAgent, 'iPod')) {
            return ['mobile', 'iPod'];
        }

        if (str_contains($userAgent, 'Android')) {
            $type = str_contains($userAgent, 'Mobile') ? 'mobile' : 'tablet';
            $fallback = $type === 'mobile' ? 'Điện thoại Android' : 'Máy tính bảng Android';

            return [$type, self::androidModel($userAgent) ?? $fallback];
        }

        if (str_contains($userAgent, 'Windows')) {
            return ['desktop', 'Máy tính Windows'];
        }

        if (str_contains($userAgent, 'Macintosh')) {
            return ['desktop', 'Máy Mac'];
        }

        if (str_contains($userAgent, 'CrOS')) {
            return ['desktop', 'Chromebook'];
        }

        if (str_contains($userAgent, 'Linux')) {
            return ['desktop', 'Máy tính Linux'];
        }

        return ['unknown', 'Không xác định'];
    }

    private static function androidModel(string $userAgent): ?string
    {
        if (preg_match('/Android [^;\)]+;\s*(?:[a-z]{2}(?:[_-][A-Z]{2})?;\s*)?([^;\)]+?)(?:\s+Build\/[^;\)]+)?[;\)]/i', $userAgent, $matches) !== 1) {
            return null;
        }

        $model = trim($matches[1]);

        return $model !== '' && ! str_starts_with(strtolower($model), 'wv') ? mb_substr($model, 0, 100) : null;
    }

    private static function operatingSystem(string $userAgent): ?string
    {
        if (preg_match('/Windows NT ([0-9.]+)/', $userAgent, $matches) === 1) {
            $versions = [
                '10.0' => 'Windows 10/11',
                '6.3' => 'Windows 8.1',
                '6.2' => 'Windows 8',
                '6.1' => 'Windows 7',
                '6.0' => 'Windows Vista',
                '5.1' => 'Windows XP',
            ];

            return $versions[$matches[1]] ?? 'Windows '.$matches[1];
        }

        if (preg_match('/(?:iPhone|CPU) OS ([0-9_]+)/', $userAgent, $matches) === 1) {
            return 'iOS '.str_replace('_', '.', $matches[1]);
        }

        if (preg_match('/Android ([0-9.]+)/', $userAgent, $matches) === 1) {
            return 'Android '.$matches[1];
        }

        if (preg_match('/Mac OS X ([0-9_\.]+)/', $userAgent, $matches) === 1) {
            return 'macOS '.str_replace('_', '.', $matches[1]);
        }

        if (preg_match('/CrOS [^ ]+ ([0-9.]+)/', $userAgent, $matches) === 1) {
            return 'Chrome OS '.$matches[1];
        }

        return str_contains($userAgent, 'Linux') ? 'Linux' : null;
    }

    private static function browser(string $userAgent): ?string
    {
        $patterns = [
            'Microsoft Edge' => '/(?:EdgA|EdgiOS|Edg)\/([0-9.]+)/',
            'Opera' => '/OPR\/([0-9.]+)/',
            'Samsung Internet' => '/SamsungBrowser\/([0-9.]+)/',
            'Chrome' => '/(?:CriOS|Chrome)\/([0-9.]+)/',
            'Firefox' => '/(?:FxiOS|Firefox)\/([0-9.]+)/',
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $userAgent, $matches) === 1) {
                return $name.' '.$matches[1];
            }
        }

        if (str_contains($userAgent, 'Safari/') && preg_match('/Version\/([0-9.]+)/', $userAgent, $matches) === 1) {
            return 'Safari '.$matches[1];
        }

        if (preg_match('/(?:MSIE |Trident\/.*rv:)([0-9.]+)/', $userAgent, $matches) === 1) {
            return 'Internet Explorer '.$matches[1];
        }

        if (preg_match('/(Googlebot|bingbot)\/([0-9.]+)/i', $userAgent, $matches) === 1) {
            return ucfirst(strtolower($matches[1])).' '.$matches[2];
        }

        return null;
    }

    /**
     * @return array{device_type: null, device_name: null, operating_system: null, browser: null}
     */
    private static function emptyResult(): array
    {
        return [
            'device_type' => null,
            'device_name' => null,
            'operating_system' => null,
            'browser' => null,
        ];
    }
}
