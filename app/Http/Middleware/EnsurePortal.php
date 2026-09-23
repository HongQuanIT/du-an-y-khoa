<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\PortalAccess;
use App\Support\Enums\PortalGroup;
use App\Support\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic portal boundary used by both system and custom roles.
 */
final class EnsurePortal
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $expected = PortalGroup::tryFrom($portal);

        if ($expected === null) {
            abort(500, "Portal [{$portal}] chưa được cấu hình.");
        }

        if (PortalAccess::allows($request->user(), $expected)) {
            return $next($request);
        }

        // Legacy content links inside shared admin views may still point to
        // /admin. Keep editor navigation inside its own portal while those
        // screens are being migrated to editor route names.
        if ($expected === PortalGroup::Admin
            && PortalAccess::allows($request->user(), PortalGroup::Editor)
            && $this->isEditorContentPath($request)) {
            if ($request->isMethodSafe()) {
                $editorPath = '/editor/'.ltrim(substr($request->path(), strlen('admin/')), '/');
                $query = $request->getQueryString();

                return redirect()->to($query ? $editorPath.'?'.$query : $editorPath);
            }

            // Transitional writes still use the existing content controllers.
            return $next($request);
        }

        if ($request->expectsJson()) {
            return ApiResponse::error(
                code: 'PORTAL_ACCESS_DENIED',
                message: 'Tài khoản không có quyền truy cập cổng này.',
                status: 403,
            );
        }

        abort(403, PortalAccess::primaryPortal($request->user()) === null
            ? 'Tài khoản chưa được gán vai trò truy cập.'
            : 'Tài khoản không có quyền truy cập cổng này.');
    }

    private function isEditorContentPath(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return str_starts_with($path, 'admin/questions')
            || str_starts_with($path, 'admin/taxonomy')
            || str_starts_with($path, 'admin/blueprints')
            || str_starts_with($path, 'admin/blueprint-sections')
            || str_starts_with($path, 'admin/core-clinical-topics')
            || str_starts_with($path, 'admin/categories')
            || str_starts_with($path, 'admin/tags')
            || str_starts_with($path, 'admin/cms')
            || str_starts_with($path, 'admin/media');
    }
}
