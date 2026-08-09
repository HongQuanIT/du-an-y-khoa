<x-layouts.admin title="Người dùng">
    <x-admin.page-header title="Người dùng"
        description="Tìm kiếm, lọc và quản lý tài khoản trên hệ thống." />

    <x-admin.flash />

    <form method="get" action="{{ route('admin.users.index') }}"
        class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="q">Tìm kiếm</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tên hoặc email"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="role">Vai trò</label>
            <select id="role" name="role"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="status">Trạng thái</label>
            <select id="status" name="status"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-4 flex gap-2">
            <button type="submit"
                class="rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary hover:opacity-90">Lọc</button>
            <a href="{{ route('admin.users.index') }}"
                class="rounded-lg px-4 py-2 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Người dùng</th>
                    <th class="px-4 py-3">Vai trò</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-label-md text-label-md text-on-surface">{{ $user->name }}</div>
                            <div class="font-label-sm text-label-sm text-on-surface-variant">#{{ $user->id }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ \App\Support\Enums\Role::tryFromName($user->primaryRoleName())?->label() ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ ($user->status ?? \App\Support\Enums\UserStatus::Active)->label() }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-on-surface">{{ $user->email }}</span>
                            @if ($user->email_verified_at)
                                <span class="ms-1 font-label-sm text-label-sm text-primary">✓</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('admin.users.show', $user) }}"
                                class="font-label-md text-label-md text-primary hover:underline">Chi tiết</a>
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
