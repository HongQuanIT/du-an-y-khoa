<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivitySession;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Actions\CreateUserAction;
use Modules\Admin\Actions\ResetUserTwoFactorAction;
use Modules\Admin\Actions\SendUserPasswordResetAction;
use Modules\Admin\Actions\UpdateUserRoleAction;
use Modules\Admin\Actions\UpdateUserStatusAction;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\StaffGuard;
use Modules\Admin\Support\AdminQuestionListQuery;
use Modules\Admin\Support\AssignableRoles;
use Modules\Auth\Models\AdministrativeUnit;
use Modules\Auth\Models\EducationStage;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\Profession;
use Modules\Partner\Models\Partner;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Subject;
use Spatie\Permission\Models\Role as RoleModel;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAnyPermission(['user.view']);

        $query = User::query()->with([
            'roles',
            'twoFactorSecret',
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

        $portals = AdminQuestionListQuery::stringValues($request->query('portal'));
        $roles = AdminQuestionListQuery::stringValues($request->query('role'));
        $statuses = AdminQuestionListQuery::stringValues($request->query('status'));

        if ($portals !== []) {
            $portalRoles = RoleModel::query()
                ->where('guard_name', 'web')
                ->whereIn('portal', $portals)
                ->pluck('name')
                ->all();
            if ($portalRoles !== []) {
                $query->role(array_values(array_unique($portalRoles)));
            }
        }

        if ($roles !== []) {
            $query->role($roles);
        }

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $twoFactorFilter = $request->query('two_factor');
        if ($twoFactorFilter === 'enabled') {
            $query->whereHas('twoFactorSecret', fn ($q) => $q->whereNotNull('confirmed_at'));
        } elseif ($twoFactorFilter === 'disabled') {
            $query->where(function ($q): void {
                $q->whereDoesntHave('twoFactorSecret')
                    ->orWhereHas('twoFactorSecret', fn ($sq) => $sq->whereNull('confirmed_at'));
            });
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
            'roles' => RoleModel::query()->where('guard_name', 'web')->orderBy('name')->get(),
            'portals' => PortalGroup::cases(),
            'statuses' => UserStatus::cases(),
            'institutions' => Institution::query()->active()->orderBy('name')->get(['id', 'name']),
            'administrativeUnits' => AdministrativeUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'professions' => Profession::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'educationStages' => EducationStage::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'canCreate' => $this->actor()->canAny(['user.create'])
                && AssignableRoles::for($this->actor())->isNotEmpty(),
            'filters' => [
                'q' => $search,
                'portal' => $portals,
                'role' => $roles,
                'status' => $statuses,
                'two_factor' => $twoFactorFilter,
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
        $this->authorizeAnyPermission(['user.create']);

        return view('admin::users.create', [
            'assignableRoles' => AssignableRoles::for($this->actor())->all(),
        ]);
    }

    public function store(Request $request, CreateUserAction $action): RedirectResponse
    {
        $this->authorizeAnyPermission(['user.create']);

        $assignableRoles = AssignableRoles::for($this->actor());
        $assignable = $assignableRoles->pluck('name')->all();

        $data = $request->validate([
            'portal' => ['required', 'string', Rule::in(PortalGroup::values())],
            'role' => ['required', 'string', Rule::in($assignable)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        /** @var RoleModel $role */
        $role = $assignableRoles->firstWhere('name', $data['role']);

        if ($role->portal !== $data['portal']) {
            return back()
                ->withInput()
                ->withErrors(['role' => 'Vai trò không thuộc portal đã chọn.']);
        }

        $user = $action->handle($this->actor(), $data, $role);

        if ($role->portal === PortalGroup::Partner->value) {
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
        $this->authorizeAnyPermission(['user.view']);

        $user->load([
            'roles',
            'learnerProfile.country',
            'learnerProfile.administrativeUnit',
            'learnerProfile.institution',
            'learnerProfile.profession',
            'learnerProfile.educationStage',
            'socialAccounts',
            'instructorSubjects',
        ]);

        $activities = UserActivitySession::query()
            ->where('user_id', $user->getKey())
            ->latest('last_seen_at')
            ->limit(20)
            ->get();

        $canTarget = $this->actor()->isNot($user);
        $canAssignRole = $canTarget
            && $this->actor()->canAny(['user.role_assign']);
        $canUpdateStatus = $canTarget
            && $this->actor()->canAny(['user.status_update']);
        $canResetPassword = $canTarget
            && $this->actor()->canAny(['user.password_reset']);
        $canTwoFactorManage = $canTarget
            && $this->actor()->canAny(['user.two_factor_manage']);
        $canDelete = $canTarget
            && $this->actor()->canAny(['user.delete']);
        $canManageInstructorSubjects = $canTarget
            && $user->hasRole(Role::Instructor->value)
            && $this->actor()->canAny(['user.role_assign', 'user.status_update']);

        return view('admin::users.show', [
            'user' => $user,
            'assignableRoles' => AssignableRoles::for($this->actor())->all(),
            'statuses' => UserStatus::cases(),
            'activities' => $activities,
            'canManage' => $canAssignRole || $canUpdateStatus || $canResetPassword || $canTwoFactorManage || $canManageInstructorSubjects || $canDelete,
            'canAssignRole' => $canAssignRole,
            'canUpdateStatus' => $canUpdateStatus,
            'canResetPassword' => $canResetPassword,
            'canTwoFactorManage' => $canTwoFactorManage,
            'canDelete' => $canDelete,
            'canManageInstructorSubjects' => $canManageInstructorSubjects,
            'subjects' => Subject::query()
                ->where('status', TaxonomyStatus::Active)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'isInstructor' => $user->hasRole(Role::Instructor->value),
        ]);
    }

    public function resetTwoFactor(User $user, ResetUserTwoFactorAction $action): RedirectResponse
    {
        $this->authorizeAnyPermission(['user.two_factor_manage']);

        $action->handle($this->actor(), $user);

        return back()->with('status', 'Đã đặt lại / tắt xác thực hai bước (2FA) cho tài khoản.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeAnyPermission(['user.delete']);
        abort_if($this->actor()->is($user), 422, 'Không thể xóa chính tài khoản đang đăng nhập.');
        StaffGuard::assertCanManage($this->actor(), $user);

        $before = AuditSnapshot::user($user);
        Auditor::record(AuditAction::UserDeleted, $this->actor(), $user, $before, []);
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'Đã xóa tài khoản. Có thể khôi phục dữ liệu từ hệ thống khi cần.');
    }

    public function updateInstructorSubjects(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAnyPermission(['user.role_assign', 'user.status_update']);
        abort_unless($user->hasRole(Role::Instructor->value), 404);

        $data = $request->validate([
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        $user->instructorSubjects()->sync(
            collect($data['subject_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all(),
        );

        return back()->with('status', 'Đã cập nhật môn học chuyên môn của giảng viên.');
    }

    public function updateRole(Request $request, User $user, UpdateUserRoleAction $action): RedirectResponse
    {
        $this->authorizeAnyPermission(['user.role_assign']);

        $assignableRoles = AssignableRoles::for($this->actor());
        $assignable = $assignableRoles->pluck('name')->all();

        $data = $request->validate([
            'portal' => ['required', 'string', Rule::in(PortalGroup::values())],
            'role' => ['required', 'string', Rule::in($assignable)],
        ]);

        /** @var RoleModel $role */
        $role = $assignableRoles->firstWhere('name', $data['role']);

        if ($role->portal !== $data['portal']) {
            return back()->withErrors(['role' => 'Vai trò không thuộc portal đã chọn.']);
        }

        $action->handle($this->actor(), $user, $role);

        return back()->with('status', 'Đã cập nhật vai trò.');
    }

    public function updateStatus(Request $request, User $user, UpdateUserStatusAction $action): RedirectResponse
    {
        $this->authorizeAnyPermission(['user.status_update']);

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
        $this->authorizeAnyPermission(['user.password_reset']);

        $action->handle($this->actor(), $user);

        return back()->with('status', 'Đã gửi email đặt lại mật khẩu (nếu cấu hình mail hoạt động).');
    }

    /** @param list<string> $permissions */
    private function authorizeAnyPermission(array $permissions): void
    {
        abort_unless($this->actor()->canAny($permissions), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
