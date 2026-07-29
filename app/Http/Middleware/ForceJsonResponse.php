<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces every API request to be treated as JSON.
 *
 * Guarantees `Request::expectsJson()` is true across the api group so that
 * framework defaults (auth, validation, 404) always emit JSON instead of
 * attempting an HTML redirect to a non-existent `login` route. This keeps the
 * API contract JSON-only per kien-truc §3-4.
 */
final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
