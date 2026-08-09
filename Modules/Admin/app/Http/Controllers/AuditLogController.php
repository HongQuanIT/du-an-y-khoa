<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Models\AuditLog;

final class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::AuditView);

        $query = AuditLog::query()->with('actor')->latest('id');

        if ($action = trim((string) $request->query('action', ''))) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($actorId = $request->query('actor_id')) {
            $query->where('actor_id', (int) $actorId);
        }

        if ($ip = trim((string) $request->query('ip', ''))) {
            $query->where('ip', 'like', "%{$ip}%");
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin::audit.index', [
            'logs' => $logs,
            'filters' => [
                'action' => $request->query('action'),
                'actor_id' => $request->query('actor_id'),
                'ip' => $request->query('ip'),
            ],
        ]);
    }

    public function show(AuditLog $audit): View
    {
        $this->authorizePermission(Permission::AuditView);

        $audit->load('actor');

        return view('admin::audit.show', [
            'log' => $audit,
        ]);
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
