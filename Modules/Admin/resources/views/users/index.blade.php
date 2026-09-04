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
        class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-2 xl:grid-cols-12">
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
            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="portal">Cổng truy cập</label>
            <select id="portal" name="portal"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Tất cả</option>
                @foreach ($portals as $portal)
                    <option value="{{ $portal->value }}" @selected($filters['portal'] === $portal->value)>{{ $portal->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="xl:col-span-2">
            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="role">Vai trò</label>
            <select id="role" name="role"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Tất cả</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="xl:col-span-2">
            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="status">Trạng thái</label>
            <select id="status" name="status"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Tất cả</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2 sm:col-span-2 xl:col-span-2">
            <button type="submit"
                class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 rounded-lg bg-primary px-4 font-label-md font-medium text-on-primary transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
                Lọc
            </button>
            <a href="{{ route('admin.users.index') }}"
                class="inline-flex h-11 flex-1 items-center justify-center whitespace-nowrap rounded-lg border border-outline-variant px-3 font-label-md font-medium text-on-surface-variant transition hover:bg-surface-container-low focus:outline-none focus:ring-2 focus:ring-primary/20">Xóa lọc</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <caption class="sr-only">Danh sách tài khoản người dùng trong hệ thống</caption>
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Người dùng</th>
                    <th class="px-4 py-3">Cổng truy cập / Vai trò</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    @php $roleEnum = \App\Support\Enums\Role::tryFromName($user->primaryRoleName()); @endphp
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 font-label-md font-semibold uppercase text-primary" aria-hidden="true">
                                    {{ mb_substr(trim($user->name), 0, 1) }}
                                </span>
                                <div class="min-w-0">
                                    <div class="truncate font-label-md font-medium text-on-surface">{{ $user->name }}</div>
                                    <div class="font-label-sm text-on-surface-variant">Mã #{{ $user->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            @if ($roleEnum)
                                <div>{{ $roleEnum->portal()->label() }}</div>
                                <div class="font-label-sm text-label-sm">{{ $roleEnum->label() }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            <span class="inline-flex rounded-full bg-surface-container px-2.5 py-1 font-label-sm font-medium text-on-surface">
                                {{ ($user->status ?? \App\Support\Enums\UserStatus::Active)->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-on-surface">{{ $user->email }}</span>
                            @if ($user->email_verified_at)
                                <span class="ms-1 font-label-sm text-label-sm text-primary">✓</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('admin.users.show', $user) }}"
                                class="inline-flex h-9 items-center justify-center rounded-lg border border-outline-variant px-3 font-label-sm font-medium text-on-surface transition hover:border-primary hover:text-primary">
                                Chi tiết
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-on-surface-variant">Không có người dùng khớp bộ lọc.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.admin>
