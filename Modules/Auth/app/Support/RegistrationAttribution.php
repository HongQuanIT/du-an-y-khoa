<?php

declare(strict_types=1);

namespace Modules\Auth\Support;

use Illuminate\Http\Request;

final class RegistrationAttribution
{
    public const SESSION_KEY = 'registration_attribution';

    public static function capture(Request $request): void
    {
        $current = (array) $request->session()->get(self::SESSION_KEY, []);

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                $current[$key] = mb_substr($value, 0, in_array($key, ['utm_campaign', 'utm_content'], true) ? 160 : 120);
            }
        }

        $current['landing_page'] ??= mb_substr($request->fullUrl(), 0, 2000);
        $current['referrer_url'] ??= $request->headers->get('referer')
            ? mb_substr((string) $request->headers->get('referer'), 0, 2000)
            : null;

        $request->session()->put(self::SESSION_KEY, $current);
    }

    /** @return array<string, string|null> */
    public static function get(Request $request): array
    {
        return (array) $request->session()->get(self::SESSION_KEY, []);
    }
}
