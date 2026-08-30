<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Auth\Enums\LoginPortal;

/**
 * After login, only honor intended URLs that belong to the same portal.
 */
final class PortalRedirect
{
    public static function afterLogin(Request $request, string $fallback, LoginPortal $portal): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended', $fallback);
        $path = parse_url($intended, PHP_URL_PATH) ?: '';

        $intendedPortal = self::portalForPath($path);

        if ($intendedPortal !== $portal) {
            return redirect()->to($fallback);
        }

        return redirect()->to($intended);
    }

    private static function portalForPath(string $path): LoginPortal
    {
        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return LoginPortal::Admin;
        }

        if ($path === '/teach' || str_starts_with($path, '/teach/')) {
            return LoginPortal::Instructor;
        }

        if ($path === '/partner' || str_starts_with($path, '/partner/')) {
            return LoginPortal::Partner;
        }

        return LoginPortal::Student;
    }
}
