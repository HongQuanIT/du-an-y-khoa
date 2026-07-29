<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetLocale;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
        then: function (): void {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trace id on every request (all groups).
        $middleware->prepend(AssignRequestId::class);

        // Locale resolution for browser + API clients.
        $middleware->web(append: [SetLocale::class]);

        // API is JSON-only: force JSON negotiation, then resolve locale.
        $middleware->api(prepend: [ForceJsonResponse::class]);
        $middleware->api(append: [SetLocale::class]);

        // Default API rate limit (per-user/IP) — see AppServiceProvider limiters.
        $middleware->throttleApi('api');

        // Route middleware aliases.
        $middleware->alias([
            'subscription' => EnsureSubscriptionActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Treat every API request as JSON so framework defaults (auth, validation)
        // never try to redirect to a non-existent `login` route.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Render every API error through the standard envelope (§3-4 conventions).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error(
                    'VALIDATION_ERROR',
                    'Dữ liệu không hợp lệ.',
                    422,
                    collect($e->errors())->flatMap(fn (array $messages, string $field) => array_map(
                        fn (string $message) => ['field' => $field, 'message' => $message],
                        $messages,
                    ))->values()->all(),
                ),
                $e instanceof AuthenticationException => ApiResponse::error(
                    'UNAUTHENTICATED', 'Chưa đăng nhập hoặc token hết hạn.', 401,
                ),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    'NOT_FOUND', 'Không tìm thấy tài nguyên.', 404,
                ),
                $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                    'RATE_LIMITED', 'Vượt quá giới hạn yêu cầu.', 429,
                ),
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    'HTTP_ERROR', $e->getMessage() ?: 'Yêu cầu không hợp lệ.', $e->getStatusCode(),
                ),
                default => app()->hasDebugModeEnabled()
                    ? null
                    : ApiResponse::error('SERVER_ERROR', 'Lỗi hệ thống.', 500),
            };
        });
    })->create();
