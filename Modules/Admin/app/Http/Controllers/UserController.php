<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivitySession;
use App\Support\Enums\Permission;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Actions\CreateUserAction;
use Modules\Admin\Actions\SendUserPasswordResetAction;
use Modules\Admin\Actions\UpdateUserRoleAction;
use Modules\Admin\Actions\UpdateUserStatusAction;
use Modules\Admin\Actions\VerifyUserEmailAction;
use Modules\Auth\Models\AdministrativeUnit;
use Modules\Auth\Models\EducationStage;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\Profession;
use Modules\Partner\Models\Partner;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::UserView);

        $query = User::query()->with([
            'roles',
            'learnerProfile.institution',
            'learnerProfile.administrativeUnit',
            'learnerProfile.profession',
            'learnerProfile.educationStage',
        ])->latest('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($portal = $request->query('portal')) {
            $portalEnum = PortalGroup::tryFrom((string) $portal);
            if ($portalEnum !== null) {
                $query->role(array_map(
                    static fn (Role $role): string => $role->value,
                    Role::rolesIn($portalEnum),
                ));
            }
        } elseif ($role = $request->query('role')) {
            $query->role((string) $role);
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        foreach (['institution_id', 'administrative_unit_id', 'profession_id', 'education_stage_id'] as $field) {
            if ($request->filled($field)) {
                $query->whereHas('learnerProfile', fn ($profile) => $profile->where($field, $request->integer($field)));
            }
        }

        if ($request->filled('onboarding')) {
            $request->string('onboarding')->toString() === 'completed'
                ? $query->whereHas('learnerProfile', fn ($profile) => $profile->whereNotNull('onboarding_completed_at'))
                : $query->whereHas('learnerProfile', fn ($profile) => $profile->whereNull('onboarding_completed_at'));
        }

        $users = $query->paginate(20)->withQueryString();

        return view('admin::users.index', [
            'users' => $users,
            'roles' => Role::cases(),
            'portals' => PortalGroup::cases(),
            'statuses' => UserStatus::cases(),
            'institutions' => Institution::query()->active()->orderBy('name')->get(['id', 'name']),
            'administrativeUnits' => AdministrativeUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'professions' => Profession::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'educationStages' => EducationStage::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'canCreate' => $this->actor()->can(Permission::UserManage->value)
                && Role::assignableBy($this->actor()) !== [],
            'filters' => [
                'q' => $search,
                'portal' => $request->query('portal'),
                'role' => $request->query('role'),
                'status' => $request->query('status'),
                'institution_id' => $request->query('institution_id'),
                'administrative_unit_id' => $request->query('administrative_unit_id'),
                'profession_id' => $request->query('profession_id'),
                'education_stage_id' => $request->query('education_stage_id'),
                'onboarding' => $request->query('onboarding'),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission(Permission::UserManage);

        return view('admin::users.create', [
            'assignableRoles' => Role::assignableBy($this->actor()),
        ]);
    }

    public function store(Request $request, CreateUserAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::UserManage);

        $assignable = array_map(
            static fn (Role $role): string => $role->value,
            Role::assignableBy($this->actor()),
        );

        $data = $request->validate([
            'portal' => ['required', 'string', Rule::in(PortalGroup::values())],
            'role' => ['required', 'string', Rule::in($assignable)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $role = Role::from($data['role']);

        if ($role->portal()->value !== $data['portal']) {
            return back()
                ->withInput()
                ->withErrors(['role' => 'Vai trò không thuộc portal đã chọn.']);
        }

        $user = $action->handle($this->actor(), $data, $role);

        if ($role === Role::Partner) {
            $partner = Partner::query()->where('user_id', $user->getKey())->firstOrFail();

            return redirect()
                ->route('admin.partners.show', $partner)
                ->with('status', 'Đã tạo tài khoản và hồ sơ CTV.');
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'Đã tạo người dùng.');
    }

    public function show(User $user): View
    {
        $this->authorizePermission(Permission::UserView);

        $user->load([
            'roles',
            'learnerProfile.country',
            'learnerProfile.administrativeUnit',
            'learnerProfile.institution',
            'learnerProfile.profession',
            'learnerProfile.educationStage',
            'socialAccounts',
        ]);

        $activities = UserActivitySession::query()
            ->where('user_id', $user->getKey())
            ->latest('last_seen_at')
            ->limit(20)
            ->get();

        return view('admin::users.show', [
            'user' => $user,
            'assignableRoles' => Role::assignableBy($this->actor()),
            'statuses' => UserStatus::cases(),
            'activities' => $activities,
            'canManage' => $this->actor()->can(Permission::UserManage->value)
                && $this->actor()->isNot($user),
        ]);
    }

    public function updateRole(Request $request, User $user, UpdateUserRoleAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::UserManage);

        $assignable = array_map(
            static fn (Role $role): string => $role->value,
            Role::assignableBy($this->actor()),
        );

        $data = $request->validate([
            'portal' => ['required', 'string', Rule::in(PortalGroup::values())],
            'role' => ['required', 'string', Rule::in($assignable)],
        ]);

        $role = Role::from($data['role']);

        if ($role->portal()->value !== $data['portal']) {
            return back()->withErrors(['role' => 'Vai trò không thuộc portal đã chọn.']);
        }

        $action->handle($this->actor(), $user, $role);

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
