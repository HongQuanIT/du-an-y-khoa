@php
    $hasActiveFilters = filled($filters['q'])
        || filled($filters['portal'] ?? [])
        || filled($filters['role'] ?? [])
        || filled($filters['status'] ?? [])
        || filled($filters['two_factor'] ?? null)
        || filled($filters['institution_id'] ?? null)
        || filled($filters['administrative_unit_id'] ?? null)
        || filled($filters['profession_id'] ?? null)
        || filled($filters['education_stage_id'] ?? null)
        || filled($filters['onboarding'] ?? null);
@endphp

<x-layouts.admin title="Người dùng">
    <div x-data="adminUserFilter()" class="space-y-6">
    <x-admin.page-header title="Người dùng"
        description="Tìm kiếm, lọc và quản lý tài khoản trên hệ thống.">
        <x-slot:actions>
            @if ($canCreate)
                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.users.create'))
<a href="{{ route('admin.users.create') }}"
                    class="rounded-lg bg-primary px-3 py-2 font-label-md text-label-md text-on-primary hover:opacity-90">Tạo người dùng</a>
@endif
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.users.index'))
<form method="get" action="{{ route('admin.users.index') }}" id="user-filter-form" role="search"
        aria-labelledby="user-filter-heading" aria-describedby="user-filter-description"
        @submit.prevent="applyFilters()"
        class="mb-6 space-y-4 rounded-xl border border-outline-variant bg-surface p-4">
        <div>
            <h2 id="user-filter-heading" class="font-label-lg font-semibold text-on-surface">Tìm kiếm người dùng</h2>
            <p id="user-filter-description" class="mt-1 font-body-sm text-on-surface-variant">
                Tìm theo tên hoặc email, sau đó lọc theo cổng truy cập, vai trò và trạng thái tài khoản.
            </p>
        </div>
        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-12">
            <div class="sm:col-span-2 xl:col-span-4">
                <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="q">Tìm kiếm</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant" aria-hidden="true">search</span>
                    <input id="q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tên hoặc địa chỉ email"
                        autocomplete="off"
                        class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-10 pr-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
            </div>
            <div class="xl:col-span-2">
                <x-admin.multi-select-filter
                    name="portal"
                    label="Cổng truy cập"
                    placeholder="Tất cả"
                    :options="collect($portals)->map(fn ($portal) => ['id' => $portal->value, 'label' => $portal->label()])->all()"
                    :selected="$filters['portal'] ?? []"
                />
            </div>
            <div class="xl:col-span-2">
                <x-admin.multi-select-filter
                    name="role"
                    label="Vai trò"
                    placeholder="Tất cả"
                    :options="collect($roles)->map(fn ($role) => ['id' => $role->name, 'label' => \Modules\Admin\Support\PermissionCatalog::roleLabel($role)])->all()"
                    :selected="$filters['role'] ?? []"
                />
            </div>
            <div class="xl:col-span-2">
                <x-admin.multi-select-filter
                    name="status"
                    label="Trạng thái"
                    placeholder="Tất cả"
                    :options="collect($statuses)->map(fn ($status) => ['id' => $status->value, 'label' => $status->label()])->all()"
                    :selected="$filters['status'] ?? []"
                />
            </div>
            <div class="xl:col-span-2">
                <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="two_factor">Bảo mật 2FA</label>
                <div class="relative">
                    <select id="two_factor" name="two_factor"
                        class="h-11 w-full appearance-none rounded-lg border border-outline-variant bg-surface-container-low px-3 pr-10 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Tất cả</option>
                        <option value="enabled" @selected(($filters['two_factor'] ?? '') === 'enabled')>Đã bật 2FA</option>
                        <option value="disabled" @selected(($filters['two_factor'] ?? '') === 'disabled')>Chưa bật 2FA</option>
                    </select>
                    <span class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant" aria-hidden="true">expand_more</span>
                </div>
            </div>
        </div>
        <details @if(collect($filters)->only(['institution_id', 'administrative_unit_id', 'profession_id', 'education_stage_id', 'onboarding'])->filter()->isNotEmpty()) open @endif>
            <summary class="cursor-pointer rounded-lg py-1 font-label-sm font-semibold text-primary outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                Bộ lọc hồ sơ học viên
            </summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div>
                    <x-admin.multi-select-filter name="administrative_unit_id" label="Tỉnh/Thành phố" placeholder="Mọi tỉnh/thành"
                        :options="$administrativeUnits->map(fn ($unit) => ['id' => $unit->id, 'label' => $unit->name])->all()"
                        :selected="$filters['administrative_unit_id'] ?? []" />
                </div>
                <div>
                    <x-admin.multi-select-filter name="institution_id" label="Trường" placeholder="Mọi trường"
                        :options="$institutions->map(fn ($institution) => ['id' => $institution->id, 'label' => $institution->name])->all()"
                        :selected="$filters['institution_id'] ?? []" />
                </div>
                <div>
                    <x-admin.multi-select-filter name="profession_id" label="Chức danh" placeholder="Mọi chức danh"
                        :options="$professions->map(fn ($profession) => ['id' => $profession->id, 'label' => $profession->name])->all()"
                        :selected="$filters['profession_id'] ?? []" />
                </div>
                <div>
                    <x-admin.multi-select-filter name="education_stage_id" label="Năm học" placeholder="Mọi năm học"
                        :options="$educationStages->map(fn ($stage) => ['id' => $stage->id, 'label' => $stage->name])->all()"
                        :selected="$filters['education_stage_id'] ?? []" />
                </div>
                <div>
                    <x-admin.multi-select-filter name="onboarding" label="Hồ sơ" placeholder="Mọi hồ sơ"
                        :options="[
                            ['id' => 'completed', 'label' => 'Đã hoàn thiện'],
                            ['id' => 'incomplete', 'label' => 'Chưa hoàn thiện'],
                        ]"
                        :selected="$filters['onboarding'] ?? []" />
                </div>
            </div>
        </details>
        <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
            <button type="submit" :disabled="loading" aria-label="Tìm kiếm người dùng"
                class="inline-flex h-11 w-36 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 font-label-md font-medium text-on-primary transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:opacity-50">
                <span class="material-symbols-outlined shrink-0 text-[18px]" aria-hidden="true"
                    x-text="loading ? 'progress_activity' : 'search'">search</span>
                <span class="whitespace-nowrap" x-text="loading ? 'Đang tải' : 'Tìm kiếm'">Tìm kiếm</span>
            </button>
            <button type="button" @click="resetFilters(@js(route('admin.users.index')))" :disabled="loading"
                aria-label="Xoá bộ lọc người dùng"
                class="inline-flex h-11 w-28 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 font-label-md font-medium text-on-surface-variant transition hover:bg-surface-container-low focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 disabled:opacity-50">
                <span class="material-symbols-outlined shrink-0 text-[18px]" aria-hidden="true">delete</span>
                <span class="whitespace-nowrap">Xoá</span>
            </button>
        </div>
    </form>
@endif

    <div id="users-results-region">
    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <div class="w-full overflow-x-auto">
            <table class="w-full min-w-[1120px] table-fixed border-collapse text-left font-body-sm text-on-surface">
                <caption class="sr-only">Danh sách tài khoản người dùng trong hệ thống</caption>
                <thead class="border-b border-outline-variant bg-surface-container-low text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                    <tr>
                        <th scope="col" class="w-[240px] px-5 py-3.5">Người dùng</th>
                        <th scope="col" class="w-[180px] px-4 py-3.5">Cổng / Vai trò</th>
                        <th scope="col" class="w-[130px] px-4 py-3.5">Trạng thái</th>
                        <th scope="col" class="w-[120px] px-4 py-3.5">2FA</th>
                        <th scope="col" class="w-[200px] px-4 py-3.5">Email</th>
                        <th scope="col" class="w-[150px] px-4 py-3.5">Đăng nhập gần nhất</th>
                        <th scope="col" class="w-[180px] px-4 py-3.5">Hồ sơ học viên</th>
                        <th scope="col" class="w-[100px] px-5 py-3.5 text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse ($users as $user)
                        @php
                            $roleModel = $user->roles->first();
                            $roleEnum = \App\Support\Enums\Role::tryFromName($user->primaryRoleName());
                            $rolePortal = $roleModel
                                ? (\App\Support\Enums\PortalGroup::tryFrom((string) $roleModel->portal) ?? $roleEnum?->portal())
                                : null;
                            $roleLabel = $roleModel
                                ? \Modules\Admin\Support\PermissionCatalog::roleLabel($roleModel)
                                : null;
                            $statusEnum = $user->status ?? \App\Support\Enums\UserStatus::Active;
                            $statusClass = match ($statusEnum) {
                                \App\Support\Enums\UserStatus::Active => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
                                \App\Support\Enums\UserStatus::Pending => 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
                                \App\Support\Enums\UserStatus::Suspended => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                \App\Support\Enums\UserStatus::Banned => 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
                            };
                        @endphp
                        <tr class="transition-colors hover:bg-surface-container-low">
                            <td class="px-5 py-3.5 align-middle">
                                <div class="flex min-w-0 items-center gap-3">
                                    @if ($user->avatarUrl())
                                        <img src="{{ $user->avatarUrl() }}" alt="Ảnh đại diện của {{ $user->name }}"
                                            class="size-9 shrink-0 rounded-full border border-outline-variant object-cover">
                                    @else
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold uppercase text-primary" aria-hidden="true">
                                            {{ mb_substr(trim($user->name), 0, 1) }}
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-on-surface" title="{{ $user->name }}">{{ $user->name }}</div>
                                        <div class="truncate text-xs text-on-surface-variant">Mã #{{ $user->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                @if ($roleModel && $rolePortal)
                                    <div class="truncate text-sm text-on-surface" title="{{ $rolePortal->label() }}">{{ $rolePortal->label() }}</div>
                                    <div class="truncate text-xs text-on-surface-variant" title="{{ $roleLabel }}">{{ $roleLabel }}</div>
                                @else
                                    <span class="text-sm text-on-surface-variant">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                <span class="inline-flex max-w-full items-center truncate rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap {{ $statusClass }}">
                                    {{ $statusEnum->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                @if ($user->hasTwoFactorEnabled())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        <span class="material-symbols-outlined text-[14px]">verified_user</span>
                                        Đã bật
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-surface-container-high px-2.5 py-0.5 text-xs font-medium text-on-surface-variant">
                                        Chưa bật
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                <div class="flex min-w-0 items-center gap-1">
                                    <span class="truncate text-sm text-on-surface" title="{{ $user->email }}">{{ $user->email }}</span>
                                    @if ($user->email_verified_at)
                                        <span class="shrink-0 text-xs text-primary" title="Email đã xác minh">✓</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                @if ($user->last_login_method)
                                    <div class="truncate text-sm text-on-surface">{{ $user->last_login_method->label() }}</div>
                                    @if ($user->last_login_at)
                                        <div class="truncate text-xs text-on-surface-variant tabular-nums">
                                            {{ $user->last_login_at->format('d/m/Y H:i') }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-xs text-on-surface-variant">Chưa có dữ liệu</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 align-middle">
                                @if ($user->learnerProfile?->onboarding_completed_at)
                                    <div class="truncate text-sm text-on-surface" title="{{ $user->learnerProfile->profession?->name }} · {{ $user->learnerProfile->educationStage?->name }}">
                                        {{ $user->learnerProfile->profession?->name ?? '—' }} · {{ $user->learnerProfile->educationStage?->name ?? '—' }}
                                    </div>
                                    <div class="truncate text-xs text-on-surface-variant" title="{{ $user->learnerProfile->institution?->name }}">
                                        {{ $user->learnerProfile->institution?->name ?? '—' }}
                                    </div>
                                @elseif ($user->learnerProfile)
                                    <span class="text-xs text-amber-700 dark:text-amber-300">Chưa hoàn thiện</span>
                                @else
                                    <span class="text-xs text-on-surface-variant">Hồ sơ cũ</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 align-middle text-end">
                                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.users.show'))
<a href="{{ route('admin.users.show', $user) }}"
                                    class="inline-flex h-8 items-center justify-center whitespace-nowrap rounded-lg border border-outline-variant px-2.5 text-xs font-medium text-on-surface transition hover:bg-surface-container-low">
                                    Chi tiết
                                </a>
@endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-on-surface-variant">Không có người dùng khớp bộ lọc.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4" id="users-pagination">{{ $users->links() }}</div>
    </div>

    <script>
        function adminUserFilter() {
            return {
                loading: false,
                filterForm() {
                    return document.querySelector('form[role="search"]');
                },
                async applyFilters() {
                    const form = this.filterForm();
                    if (!form) return;
                    const url = new URL(form.action, window.location.origin);
                    const params = new URLSearchParams(new FormData(form));
                    params.delete('page');
                    url.search = params.toString();
                    await this.fetchResults(url.toString());
                },
                async resetFilters(url) {
                    const form = this.filterForm();
                    form?.reset();
                    form?.querySelectorAll('select').forEach((select) => {
                        select.value = '';
                    });
                    const queryInput = form?.querySelector('[name="q"]');
                    if (queryInput) queryInput.value = '';
                    form?.querySelectorAll('details').forEach((details) => { details.open = false; });
                    window.dispatchEvent(new CustomEvent('user-filters-reset'));
                    await this.fetchResults(url);
                },
                async fetchResults(url) {
                    this.loading = true;
                    try {
                        const response = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'text/html',
                            },
                        });
                        if (!response.ok) throw new Error('Lỗi tải danh sách người dùng');
                        const html = await response.text();
                        const parsed = new DOMParser().parseFromString(html, 'text/html');
                        const next = parsed.getElementById('users-results-region');
                        const current = document.getElementById('users-results-region');
                        if (!next || !current) throw new Error('Không tìm thấy vùng kết quả người dùng');
                        current.replaceWith(next);
                        window.history.pushState({}, '', url);
                        this.bindPagination();
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải danh sách người dùng. Vui lòng thử lại.');
                    } finally {
                        this.loading = false;
                    }
                },
                bindPagination() {
                    document.querySelectorAll('#users-pagination a').forEach((link) => {
                        link.addEventListener('click', (event) => {
                            event.preventDefault();
                            if (link.href) this.fetchResults(link.href);
                        });
                    });
                },
                init() {
                    this.bindPagination();
                },
            };
        }
    </script>
    </div>
</x-layouts.admin>
