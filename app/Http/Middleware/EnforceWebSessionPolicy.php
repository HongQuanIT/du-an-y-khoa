<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\WebSessionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One active web session per user, 30-day cap, 24-hour idle timeout.
 */
final class EnforceWebSessionPolicy
{
    /** @var list<string> */
    private const SKIP_ROUTE_PATTERNS = [
        'login',
        'login.store',
        'register',
        'register.store',
        'password.request',
        'password.email',
        'password.reset',
        'password.update',
        'student.2fa.*',
        'admin.login*',
        'admin.logout',
        'admin.2fa.*',
        'teach.login*',
        'teach.logout',
        'teach.2fa.*',
        'partner.login*',
        'partner.logout',
        'partner.2fa.*',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($request->routeIs(...self::SKIP_ROUTE_PATTERNS)) {
            return $next($request);
        }

        WebSessionManager::ensureInitialized($user, $request);

        if (! WebSessionManager::isActiveSession($user, $request)) {
            WebSessionManager::logout(
                $request,
                'Tài khoản đã đăng nhập trên thiết bị khác. Vui lòng đăng nhập lại.',
            );

            return $this->redirectToLogin($request);
        }

        if (WebSessionManager::isExpired($request)) {
            WebSessionManager::logout(
                $request,
                'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
            );

            return $this->redirectToLogin($request);
        }

        WebSessionManager::touchActivity($request);

        return $next($request);
    }

    private function redirectToLogin(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
            ], 401);
        }

        if ($request->is('admin') || $request->is('admin/*')) {
            return redirect()->route('admin.login');
        }

        if ($request->is('teach') || $request->is('teach/*')) {
            return redirect()->route('teach.login');
        }

        if ($request->is('partner') || $request->is('partner/*')) {
            return redirect()->route('partner.login');
        }

        return redirect()->route('login');
    }
}
