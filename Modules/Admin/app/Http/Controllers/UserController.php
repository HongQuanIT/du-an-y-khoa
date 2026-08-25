<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\SendUserPasswordResetAction;
use Modules\Admin\Actions\UpdateUserRoleAction;
use Modules\Admin\Actions\UpdateUserStatusAction;
use Modules\Admin\Actions\VerifyUserEmailAction;
use Modules\Admin\Models\AuditLog;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::UserView);

        $query = User::query()->with('roles')->latest('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->role((string) $role);
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        $users = $query->paginate(20)->withQueryString();

        return view('admin::users.index', [
            'users' => $users,
            'roles' => Role::cases(),
            'statuses' => UserStatus::cases(),
            'filters' => [
                'q' => $search,
                'role' => $request->query('role'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function show(User $user): View
    {
        $this->authorizePermission(Permission::UserView);

        $user->load('roles');

        $audits = AuditLog::query()
            ->visibleToAdmin()
            ->where(function ($query) use ($user): void {
                $query->where('actor_id', $user->getKey())
                    ->orWhere(function ($subject) use ($user): void {
                        $subject->where('auditable_type', $user->getMorphClass())
                            ->where('auditable_id', (string) $user->getKey());
                    });
            })
            ->with('actor:id,name')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin::users.show', [
            'user' => $user,
            'assignableRoles' => Role::assignableBy($this->actor()),
            'statuses' => UserStatus::cases(),
            'audits' => $audits,
            'canManage' => $this->actor()->can(Permission::UserManage->value)
                && $this->actor()->isNot($user),
        ]);
    }

    public function updateRole(Request $request, User $user, UpdateUserRoleAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::UserManage);

        $data = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', Role::values())],
        ]);

        $action->handle($this->actor(), $user, Role::from($data['role']));

        return back()->with('status', 'Đã cập nhật vai trò.');
    }

    public function updateStatus(Request $request, User $user, UpdateUserStatusAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::UserManage);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', UserStatus::values())],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $action->handle(
            $this->actor(),
            $user,
            UserStatus::from($data['status']),
            $data['reason'] ?? null,
        );

        return back()->with('status', 'Đã cập nhật trạng thái tài khoản.');
    }

    public function resetPassword(User $user, SendUserPasswordResetAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::UserManage);

        $action->handle($this->actor(), $user);

        return back()->with('status', 'Đã gửi email đặt lại mật khẩu (nếu cấu hình mail hoạt động).');
    }

    public function verifyEmail(User $user, VerifyUserEmailAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::UserManage);

        $action->handle($this->actor(), $user);

        return back()->with('status', 'Đã xác minh email.');
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
