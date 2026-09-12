@php
    $hasActiveFilters = filled($filters['q'])
        || filled($filters['portal'] ?? [])
        || filled($filters['role'] ?? [])
        || filled($filters['status'] ?? [])
        || filled($filters['institution_id'] ?? null)
        || filled($filters['administrative_unit_id'] ?? null)
        || filled($filters['profession_id'] ?? null)
        || filled($filters['education_stage_id'] ?? null)
        || filled($filters['onboarding'] ?? null);
@endphp

<x-layouts.admin title="Người dùng">
    <x-admin.page-header title="Người dùng"
        description="Tìm kiếm, lọc và quản lý tài khoản trên hệ thống.">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('admin.users.create') }}"
                    class="rounded-lg bg-primary px-3 py-2 font-label-md text-label-md text-on-primary hover:opacity-90">Tạo người dùng</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <form method="get" action="{{ route('admin.users.index') }}" role="search" aria-label="Lọc danh sách người dùng"
        class="mb-6 space-y-4 rounded-xl border border-outline-variant bg-surface p-4">
        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-12">
            <div class="sm:col-span-2 xl:col-span-3">
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
                    placeholder="Tất cả cổng"
                    :options="collect($portals)->map(fn ($portal) => ['id' => $portal->value, 'label' => $portal->label()])->all()"
                    :selected="$filters['portal'] ?? []"
                />
            </div>
            <div class="xl:col-span-2">
                <x-admin.multi-select-filter
                    name="role"
                    label="Vai trò"
                    placeholder="Tất cả vai trò"
                    :options="collect($roles)->map(fn ($role) => ['id' => $role->value, 'label' => $role->label()])->all()"
                    :selected="$filters['role'] ?? []"
                />
            </div>
            <div class="xl:col-span-2">
                <x-admin.multi-select-filter
                    name="status"
                    label="Trạng thái"
                    placeholder="Tất cả trạng thái"
                    :options="collect($statuses)->map(fn ($status) => ['id' => $status->value, 'label' => $status->label()])->all()"
                    :selected="$filters['status'] ?? []"
                />
            </div>
            <div class="flex gap-2 sm:col-span-2 xl:col-span-3">
                <div class="flex-1">
                    <span class="mb-1.5 block font-label-sm font-semibold text-transparent" aria-hidden="true">Lọc</span>
                    <button type="submit"
                        class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-lg bg-primary px-4 font-label-md font-medium text-on-primary transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
                        Lọc
                    </button>
                </div>
                @if ($hasActiveFilters)
                    <div class="flex-1">
                        <span class="mb-1.5 block font-label-sm font-semibold text-transparent" aria-hidden="true">Xóa</span>
                        <a href="{{ route('admin.users.index') }}"
                            class="inline-flex h-11 w-full items-center justify-center whitespace-nowrap rounded-lg border border-outline-variant px-3 font-label-md font-medium text-on-surface-variant transition hover:bg-surface-container-low">Xóa lọc</a>
                    </div>
                @endif
            </div>
        </div>
        <details @if(collect($filters)->only(['institution_id', 'administrative_unit_id', 'profession_id', 'education_stage_id', 'onboarding'])->filter()->isNotEmpty()) open @endif>
            <summary class="cursor-pointer font-label-sm font-semibold text-primary">Bộ lọc hồ sơ học viên</summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <select name="administrative_unit_id" aria-label="Tỉnh thành" class="h-10 rounded-lg border border-outline-variant bg-surface-container-low px-3 text-body-sm"><option value="">Mọi tỉnh/thành</option>@foreach($administrativeUnits as $unit)<option value="{{ $unit->id }}" @selected((string)($filters['administrative_unit_id'] ?? '') === (string)$unit->id)>{{ $unit->name }}</option>@endforeach</select>
                <select name="institution_id" aria-label="Trường" class="h-10 rounded-lg border border-outline-variant bg-surface-container-low px-3 text-body-sm"><option value="">Mọi trường</option>@foreach($institutions as $institution)<option value="{{ $institution->id }}" @selected((string)($filters['institution_id'] ?? '') === (string)$institution->id)>{{ $institution->name }}</option>@endforeach</select>
                <select name="profession_id" aria-label="Chức danh" class="h-10 rounded-lg border border-outline-variant bg-surface-container-low px-3 text-body-sm"><option value="">Mọi chức danh</option>@foreach($professions as $profession)<option value="{{ $profession->id }}" @selected((string)($filters['profession_id'] ?? '') === (string)$profession->id)>{{ $profession->name }}</option>@endforeach</select>
                <select name="education_stage_id" aria-label="Năm học" class="h-10 rounded-lg border border-outline-variant bg-surface-container-low px-3 text-body-sm"><option value="">Mọi năm học</option>@foreach($educationStages as $stage)<option value="{{ $stage->id }}" @selected((string)($filters['education_stage_id'] ?? '') === (string)$stage->id)>{{ $stage->name }}</option>@endforeach</select>
                <select name="onboarding" aria-label="Onboarding" class="h-10 rounded-lg border border-outline-variant bg-surface-container-low px-3 text-body-sm"><option value="">Mọi hồ sơ</option><option value="completed" @selected(($filters['onboarding'] ?? '') === 'completed')>Đã hoàn thiện</option><option value="incomplete" @selected(($filters['onboarding'] ?? '') === 'incomplete')>Chưa hoàn thiện</option></select>
            </div>
        </details>
    </form>

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <div class="w-full overflow-x-auto">
            <table class="w-full min-w-[1120px] table-fixed border-collapse text-left font-body-sm text-on-surface">
                <caption class="sr-only">Danh sách tài khoản người dùng trong hệ thống</caption>
                <thead class="border-b border-outline-variant bg-surface-container-low text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                    <tr>
                        <th scope="col" class="w-[240px] px-5 py-3.5">Người dùng</th>
                        <th scope="col" class="w-[180px] px-4 py-3.5">Cổng / Vai trò</th>
                        <th scope="col" class="w-[140px] px-4 py-3.5">Trạng thái</th>
                        <th scope="col" class="w-[220px] px-4 py-3.5">Email</th>
                        <th scope="col" class="w-[160px] px-4 py-3.5">Đăng nhập gần nhất</th>
                        <th scope="col" class="w-[200px] px-4 py-3.5">Hồ sơ học viên</th>
                        <th scope="col" class="w-[112px] px-5 py-3.5 text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse ($users as $user)
                        @php
                            $roleEnum = \App\Support\Enums\Role::tryFromName($user->primaryRoleName());
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
                                @if ($roleEnum)
                                    <div class="truncate text-sm text-on-surface" title="{{ $roleEnum->portal()->label() }}">{{ $roleEnum->portal()->label() }}</div>
                                    <div class="truncate text-xs text-on-surface-variant" title="{{ $roleEnum->label() }}">{{ $roleEnum->label() }}</div>
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
                                <a href="{{ route('admin.users.show', $user) }}"
                                    class="inline-flex h-8 items-center justify-center whitespace-nowrap rounded-lg border border-outline-variant px-2.5 text-xs font-medium text-on-surface transition hover:bg-surface-container-low">
                                    Chi tiết
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-on-surface-variant">Không có người dùng khớp bộ lọc.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.admin>
