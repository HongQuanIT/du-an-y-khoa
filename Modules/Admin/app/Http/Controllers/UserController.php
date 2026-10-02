<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivitySession;
use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use App\Support\LearnerCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Actions\CreateUserAction;
use Modules\Admin\Actions\ResetUserTwoFactorAction;
use Modules\Admin\Actions\SendUserPasswordResetAction;
use Modules\Admin\Actions\UpdateLearnerProfileAction;
use Modules\Admin\Actions\UpdateUserRoleAction;
use Modules\Admin\Actions\UpdateUserStatusAction;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\AdminQuestionListQuery;
use Modules\Admin\Support\AssignableRoles;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\StaffGuard;
use Modules\Admin\Support\UserDetailLink;
use Modules\Auth\Actions\BeginTwoFactorSetupAction;
use Modules\Auth\Actions\ConfirmTwoFactorSetupAction;
use Modules\Auth\Models\AdministrativeUnit;
use Modules\Auth\Models\Country;
use Modules\Auth\Models\EducationStage;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\Profession;
use Modules\Auth\Services\TotpService;
use Modules\Partner\Models\Partner;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Subject;
use Spatie\Permission\Models\Role as RoleModel;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAnyPermission(['user.view', 'user.lookup']);

        $lookupOnly = ! $this->actor()->can('user.view');
        $search = trim((string) $request->query('q', ''));
        $lookupNotice = null;

        $query = User::query()->with([
            'roles',
            'twoFactorSecret',
            'learnerProfile.institution',
            'learnerProfile.administrativeUnit',
            'learnerProfile.profession',
            'learnerProfile.educationStage',
        ])->latest('id');

        if ($lookupOnly) {
            $lookupNotice = $this->applyLearnerEmailLookup($query, $search);
        } elseif ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $portals = $lookupOnly ? [] : AdminQuestionListQuery::stringValues($request->query('portal'));
        $roles = $lookupOnly ? [] : AdminQuestionListQuery::stringValues($request->query('role'));
        $statuses = $lookupOnly ? [] : AdminQuestionListQuery::stringValues($request->query('status'));

        if ($portals !== []) {
            $portalRoles = RoleModel::query()
                ->where('guard_name', 'web')
                ->whereIn('portal', $portals)
                ->pluck('name')
                ->all();
            $portalRoles = array_values(array_unique(array_merge(
                $portalRoles,
                collect(Role::cases())
                    ->filter(fn (Role $role): bool => in_array($role->portal()->value, $portals, true))
                    ->map(fn (Role $role): string => $role->value)
                    ->all(),
            )));
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

        $twoFactorFilters = $lookupOnly ? [] : array_values(array_intersect(
            AdminQuestionListQuery::stringValues($request->query('two_factor')),
            ['enabled', 'disabled'],
        ));
        if ($twoFactorFilters === ['enabled']) {
            $query->whereHas('twoFactorSecret', fn ($q) => $q->whereNotNull('confirmed_at'));
        } elseif ($twoFactorFilters === ['disabled']) {
            $query->where(function ($q): void {
                $q->whereDoesntHave('twoFactorSecret')
                    ->orWhereHas('twoFactorSecret', fn ($sq) => $sq->whereNull('confirmed_at'));
            });
        }

        foreach (['country_id', 'institution_id', 'administrative_unit_id', 'profession_id', 'education_stage_id'] as $field) {
            $ids = $lookupOnly ? [] : AdminQuestionListQuery::integerIds($request->query($field));
            if ($ids !== []) {
                $query->whereHas('learnerProfile', fn ($profile) => $profile->whereIn($field, $ids));
            }
        }

        $onboardingFilters = $lookupOnly ? [] : AdminQuestionListQuery::stringValues($request->query('onboarding'));
        if ($onboardingFilters !== []) {
            if ($onboardingFilters === ['completed']) {
                $query->whereHas('learnerProfile', fn ($profile) => $profile->whereNotNull('onboarding_completed_at'));
            } elseif ($onboardingFilters === ['incomplete']) {
                $query->whereHas('learnerProfile', fn ($profile) => $profile->whereNull('onboarding_completed_at'));
            }
        }

        $users = $query->paginate(20)->withQueryString();

        if ($lookupOnly && $lookupNotice === null && $users->isEmpty()) {
            $lookupNotice = 'Không tìm thấy học viên với email hoặc mã này.';
        }

        return view('admin::users.index', [
            'users' => $users,
            'lookupOnly' => $lookupOnly,
            'lookupNotice' => $lookupNotice,
            'roles' => $lookupOnly ? collect() : RoleModel::query()->where('guard_name', 'web')->orderBy('name')->get(),
            'portals' => PortalGroup::cases(),
            'statuses' => UserStatus::cases(),
            'countries' => $lookupOnly ? collect() : Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'institutions' => $lookupOnly ? collect() : Institution::query()->active()->orderBy('name')->get(['id', 'name']),
            'administrativeUnits' => $lookupOnly ? collect() : AdministrativeUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'professions' => $lookupOnly ? collect() : Profession::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'educationStages' => $lookupOnly ? collect() : EducationStage::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'canCreate' => $this->actor()->canAny(['user.create'])
                && AssignableRoles::for($this->actor())->isNotEmpty(),
            'filters' => [
                'q' => $search,
                'portal' => $portals,
                'role' => $roles,
                'status' => $statuses,
                'two_factor' => $twoFactorFilters,
                'country_id' => AdminQuestionListQuery::integerIds($request->query('country_id')),
                'institution_id' => AdminQuestionListQuery::integerIds($request->query('institution_id')),
                'administrative_unit_id' => AdminQuestionListQuery::integerIds($request->query('administrative_unit_id')),
                'profession_id' => AdminQuestionListQuery::integerIds($request->query('profession_id')),
                'education_stage_id' => AdminQuestionListQuery::integerIds($request->query('education_stage_id')),
                'onboarding' => $onboardingFilters,
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

        $rolePortal = $this->rolePortal($role);

        if ($rolePortal->value !== $data['portal']) {
            return back()
                ->withInput()
                ->withErrors(['role' => 'Vai trò không thuộc portal đã chọn.']);
        }

        $user = $action->handle($this->actor(), $data, $role);

        if ($rolePortal === PortalGroup::Partner) {
            $partner = Partner::query()->where('user_id', $user->getKey())->firstOrFail();

            return redirect()
                ->route('admin.partners.show', $partner)
                ->with('status', 'Đã tạo tài khoản và hồ sơ CTV.');
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'Đã tạo người dùng.');
    }

    public function show(Request $request, User $user): View
    {
        $this->authorizeAnyPermission(['user.view', 'user.lookup']);
        $this->assertDirectUserAccess($request, $user);

        $user->load([
            'roles',
            'twoFactorSecret',
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
        $canEditLearner = $canTarget
            && $this->actor()->can('user.update')
            && $this->isLearner($user);
        $canAssignRole = $canTarget
            && $this->actor()->canAny(['user.role_assign']);
        $canUpdateStatus = $canTarget
            && ($this->actor()->can('user.status_update') || $canEditLearner);
        $canResetPassword = $canTarget
            && ($this->actor()->can('user.password_reset') || $canEditLearner);
        $canTwoFactorManage = $canTarget
            && ($this->actor()->can('user.two_factor_manage') || $canEditLearner);
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
            'canManage' => $canAssignRole || $canEditLearner || $canUpdateStatus || $canResetPassword || $canTwoFactorManage || $canManageInstructorSubjects || $canDelete,
            'canEditLearner' => $canEditLearner,
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
            'profileCountries' => $canEditLearner
                ? Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
                : collect(),
            'profileUnits' => $canEditLearner
                ? AdministrativeUnit::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
                : collect(),
            'profileInstitutions' => $canEditLearner
                ? Institution::query()->active()->orderBy('name')->get(['id', 'name', 'country_id', 'administrative_unit_id'])
                : collect(),
            'profileProfessions' => $canEditLearner
                ? Profession::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
                : collect(),
            'profileStages' => $canEditLearner
                ? EducationStage::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
                : collect(),
            'twoFactorSetup' => $this->pendingTwoFactorSetup($user, $canTwoFactorManage),
            'recoveryCodes' => session('two_factor_recovery_codes', []),
        ]);
    }

    public function updateProfile(Request $request, User $user, UpdateLearnerProfileAction $action): RedirectResponse
    {
        $this->authorizeAnyPermission(['user.update']);
        $this->assertDirectUserAccess($request, $user);
        abort_if($this->actor()->is($user), 403);

        $data = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
            'administrative_unit_id' => ['required', 'integer', Rule::exists('administrative_units', 'id')->where('is_active', true)],
            'institution_id' => ['required', 'integer', Rule::exists('institutions', 'id')->where('is_active', true)],
            'profession_id' => ['required', 'integer', Rule::exists('professions', 'id')->where('is_active', true)],
            'education_stage_id' => ['nullable', 'integer', Rule::exists('education_stages', 'id')->where('is_active', true)],
        ]);

        $unitMatches = AdministrativeUnit::query()
            ->whereKey($data['administrative_unit_id'])
            ->where('country_id', $data['country_id'])
            ->where('is_active', true)
            ->exists();
        $institutionMatches = Institution::query()
            ->whereKey($data['institution_id'])
            ->where('country_id', $data['country_id'])
            ->where('administrative_unit_id', $data['administrative_unit_id'])
            ->where('is_active', true)
            ->exists();
        $profession = Profession::query()->findOrFail($data['profession_id']);

        if (! $unitMatches) {
            return back()->withInput()->withErrors(['administrative_unit_id' => 'Tỉnh/thành phố không thuộc quốc gia đã chọn.']);
        }

        if (! $institutionMatches) {
            return back()->withInput()->withErrors(['institution_id' => 'Trường không thuộc tỉnh/thành phố đã chọn.']);
        }

        if ($profession->requires_education_stage && ! $profession->defaults_to_graduated && empty($data['education_stage_id'])) {
            return back()->withInput()->withErrors(['education_stage_id' => 'Vui lòng chọn năm học.']);
        }

        $action->handle($this->actor(), $user, [
            'country_id' => (int) $data['country_id'],
            'administrative_unit_id' => (int) $data['administrative_unit_id'],
            'institution_id' => (int) $data['institution_id'],
            'profession_id' => (int) $data['profession_id'],
            'education_stage_id' => filled($data['education_stage_id'] ?? null) ? (int) $data['education_stage_id'] : null,
        ]);

        return redirect()
            ->to(UserDetailLink::to($this->actor(), 'admin.users.show', $user->fresh()))
            ->with('status', 'Đã cập nhật hồ sơ học viên.');
    }

    public function beginTwoFactor(Request $request, User $user, BeginTwoFactorSetupAction $action): RedirectResponse
    {
        $this->authorizeLearnerSupport($user, 'user.two_factor_manage');
        $this->assertDirectUserAccess($request, $user);
        abort_if($user->hasTwoFactorEnabled(), 422, 'Tài khoản đã bật xác thực hai bước.');

        $action->handle($user);

        return back()->with('status', 'Đã tạo mã kích hoạt 2FA. Học viên quét mã QR rồi đọc mã 6 số để xác nhận.');
    }

    public function confirmTwoFactor(Request $request, User $user, ConfirmTwoFactorSetupAction $action): RedirectResponse
    {
        $this->authorizeLearnerSupport($user, 'user.two_factor_manage');
        $this->assertDirectUserAccess($request, $user);

        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $codes = $action->handle($user, $data['code']);

        Auditor::record(AuditAction::UserTwoFactorEnabled, $this->actor(), $user, metadata: [
            'action' => 'admin_enable_2fa',
        ]);

        return back()
            ->with('status', 'Đã kích hoạt xác thực hai bước. Gửi các mã khôi phục cho học viên, mã chỉ hiện một lần.')
            ->with('two_factor_recovery_codes', $codes);
    }

    public function resetTwoFactor(Request $request, User $user, ResetUserTwoFactorAction $action): RedirectResponse
    {
        $this->authorizeLearnerSupport($user, 'user.two_factor_manage');
        $this->assertDirectUserAccess($request, $user);

        $action->handle($this->actor(), $user);

        return back()->with('status', 'Đã đặt lại / tắt xác thực hai bước (2FA) cho tài khoản.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAnyPermission(['user.delete']);
        $this->assertDirectUserAccess($request, $user);
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
        $this->assertDirectUserAccess($request, $user);
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
        $this->assertDirectUserAccess($request, $user);

        $assignableRoles = AssignableRoles::for($this->actor());
        $assignable = $assignableRoles->pluck('name')->all();

        $data = $request->validate([
            'portal' => ['required', 'string', Rule::in(PortalGroup::values())],
            'role' => ['required', 'string', Rule::in($assignable)],
        ]);

        /** @var RoleModel $role */
        $role = $assignableRoles->firstWhere('name', $data['role']);

        if ($this->rolePortal($role)->value !== $data['portal']) {
            return back()->withErrors(['role' => 'Vai trò không thuộc portal đã chọn.']);
        }

        $action->handle($this->actor(), $user, $role);

        return back()->with('status', 'Đã cập nhật vai trò.');
    }

    public function updateStatus(Request $request, User $user, UpdateUserStatusAction $action): RedirectResponse
    {
        $this->authorizeLearnerSupport($user, 'user.status_update');
        $this->assertDirectUserAccess($request, $user);

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

    public function resetPassword(Request $request, User $user, SendUserPasswordResetAction $action): RedirectResponse
    {
        $this->authorizeLearnerSupport($user, 'user.password_reset');
        $this->assertDirectUserAccess($request, $user);

        $action->handle($this->actor(), $user);

        return back()->with('status', 'Đã gửi email đặt lại mật khẩu (nếu cấu hình mail hoạt động).');
    }

    /**
     * Lookup never opens the directory. A hit requires the full email or learner code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     */
    private function applyLearnerEmailLookup($query, string $search): ?string
    {
        if ($search === '') {
            $query->whereRaw('1 = 0');

            return 'Nhập đúng email hoặc mã học viên để tra cứu. Danh sách không được hiển thị.';
        }

        $code = LearnerCode::normalize($search);
        $isCode = LearnerCode::isValid($code);
        $isEmail = filter_var($search, FILTER_VALIDATE_EMAIL) !== false;

        if (! $isCode && ! $isEmail) {
            $query->whereRaw('1 = 0');

            return 'Cần nhập đầy đủ email hoặc mã học viên (2 chữ cái và 4 số). Hệ thống không tìm theo tên hoặc một phần thông tin.';
        }

        $key = 'admin-user-lookup:'.$this->actor()->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Bạn đã tra cứu quá nhiều lần. Hãy thử lại sau một phút.');
        }
        RateLimiter::hit($key, 60);

        $learnerRoles = $this->learnerRoleNames();
        if ($learnerRoles === []) {
            $query->whereRaw('1 = 0');

            return null;
        }

        if ($isCode) {
            $query->where('learner_code', $code);
        } else {
            $query->whereRaw('LOWER(email) = ?', [mb_strtolower($search)]);
        }
        $query->role($learnerRoles);

        return null;
    }

    /** @return list<string> */
    private function learnerRoleNames(): array
    {
        return RoleModel::query()
            ->where('guard_name', 'web')
            ->where(function ($builder): void {
                $builder->where('name', Role::Student->value)
                    ->orWhere('portal', PortalGroup::Learner->value);
            })
            ->pluck('name')
            ->all();
    }

    private function assertDirectUserAccess(Request $request, User $user): void
    {
        UserDetailLink::assert($request, $this->actor());

        if (! $this->actor()->can('user.view')) {
            abort_unless($this->isLearner($user), 404);
        }
    }

    private function authorizeLearnerSupport(User $target, string $permission): void
    {
        if ($this->actor()->can($permission)) {
            return;
        }

        abort_unless($this->actor()->can('user.update') && $this->isLearner($target), 403);
        abort_if($this->actor()->is($target), 403);
    }

    /** @return array{secret: string, qr: string}|null */
    private function pendingTwoFactorSetup(User $user, bool $canManage): ?array
    {
        $secret = $user->twoFactorSecret;

        if (! $canManage || $secret === null || $secret->isConfirmed() || $secret->secret === '') {
            return null;
        }

        return [
            'secret' => $secret->secret,
            'qr' => app(TotpService::class)->qrDataUri((string) config('app.name'), $user->email, $secret->secret),
        ];
    }

    private function isLearner(User $user): bool
    {
        $user->loadMissing('roles');

        return $user->roles->contains(
            fn ($role): bool => in_array($role->name, $this->learnerRoleNames(), true),
        );
    }

    /** @param list<string> $permissions */
    private function authorizeAnyPermission(array $permissions): void
    {
        abort_unless($this->actor()->canAny($permissions), 403);
    }

    private function rolePortal(RoleModel $role): PortalGroup
    {
        return Role::tryFrom($role->name)?->portal()
            ?? PortalGroup::tryFrom((string) $role->portal)
            ?? PortalGroup::Admin;
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
