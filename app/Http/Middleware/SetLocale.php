<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale from the authenticated user, then the
 * Accept-Language header, falling back to the app default.
 */
final class SetLocale
{
    /** @var list<string> */
    private const SUPPORTED = ['vi', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $locale = ($user->locale ?? null)
            ?? $request->getPreferredLanguage(self::SUPPORTED)
            ?? config('app.locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
