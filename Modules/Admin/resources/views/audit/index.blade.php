<x-layouts.admin title="Audit">
    <x-admin.page-header title="Audit log"
        description="Nhật ký bất biến các thao tác nhạy cảm (chỉ đọc)." />

    <x-admin.flash />

    <form method="get" action="{{ route('admin.audit.index') }}" role="search" aria-label="Lọc nhật ký hoạt động"
        class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-2 xl:grid-cols-12">
        <div class="relative min-w-0 xl:col-span-3"
            x-data='{
                open: false,
                query: @json((string) ($filters["action"] ?? "")),
                items: @json($actionSuggestions),
                matches() {
                    const keyword = this.query.trim().toLocaleLowerCase();
                    const rows = keyword === ""
                        ? this.items
                        : this.items.filter(item =>
                            item.label.toLocaleLowerCase().includes(keyword)
                            || item.value.toLocaleLowerCase().includes(keyword)
                        );

                    return rows.slice(0, 8);
                }
            }'
            @click.outside="open = false">
            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="action">Hành động</label>
            <div class="relative">
                <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                <input id="action" name="action" x-model="query" type="search"
                    @focus="open = true" @input="open = true" @keydown.escape="open = false"
                    placeholder="Nhập mã hoặc tên hành động" autocomplete="new-password" autocapitalize="none"
                    spellcheck="false" data-form-type="other" data-lpignore="true" data-1p-ignore="true"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-9 pr-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
            </div>

            <div x-show="open && matches().length > 0" x-cloak
                class="absolute inset-x-0 z-40 mt-1 max-h-72 overflow-y-auto rounded-xl border border-outline-variant bg-surface p-1.5 shadow-xl">
                <template x-for="item in matches()" :key="item.value">
                    <button type="button"
                        @click="query = item.value; open = false; $nextTick(() => $el.closest('form').requestSubmit())"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[18px] text-primary">bolt</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-on-surface" x-text="item.label"></span>
                            <span class="block truncate font-mono text-[11px] text-on-surface-variant" x-text="item.value"></span>
                        </span>
                    </button>
                </template>
            </div>
        </div>
        <div class="min-w-0 xl:col-span-2">
            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="actor">Người thực hiện</label>
            <input id="actor" name="actor" value="{{ $filters['actor'] }}" type="search"
                placeholder="Nhập tên hoặc ID" autocomplete="off"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="min-w-0 xl:col-span-2">
            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="actor_role">Vai trò</label>
            <select id="actor_role" name="actor_role"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Tất cả</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected($filters['actor_role'] === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-0 xl:col-span-3">
            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="ip">Địa chỉ IP</label>
            <input id="ip" name="ip" value="{{ $filters['ip'] }}" type="search"
                placeholder="Ví dụ: 192.168.1.1" autocomplete="off" spellcheck="false"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="grid grid-cols-2 gap-2 sm:col-span-2 xl:col-span-2">
            <button type="submit"
                class="inline-flex h-11 items-center justify-center whitespace-nowrap rounded-lg bg-primary px-4 font-label-md text-on-primary transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30">
                Lọc
            </button>
            <a href="{{ route('admin.audit.index') }}"
                class="inline-flex h-11 items-center justify-center whitespace-nowrap rounded-lg border border-outline-variant px-4 font-label-md text-on-surface-variant transition hover:bg-surface-container-low focus:outline-none focus:ring-2 focus:ring-primary/20">
                Xóa lọc
            </a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Thời gian</th>
                    <th class="px-4 py-3">Người thực hiện</th>
                    <th class="px-4 py-3">Hành động</th>
                    <th class="px-4 py-3">Đối tượng</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3">Thiết bị truy cập</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3 whitespace-nowrap text-on-surface-variant">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3">
                            @if ($log->actor)
                                <a href="{{ route('admin.users.show', $log->actor) }}" class="text-primary hover:underline">{{ $log->actor->name }}</a>
                                <div class="font-label-sm text-label-sm text-on-surface-variant">#{{ $log->actor_id }}</div>
                                <div class="whitespace-nowrap font-label-sm text-label-sm text-on-surface-variant">{{ $log->actor_role ?? '—' }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="whitespace-nowrap font-label-md text-on-surface">{{ $log->actionLabel() }}</div>
                            <div class="whitespace-nowrap font-mono text-xs text-on-surface-variant">{{ $log->action }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            @if ($log->auditable_type)
                                @if ($log->auditable_type === $userMorphClass)
                                    <a href="{{ route('admin.users.show', $log->auditable_id) }}" class="whitespace-nowrap text-primary hover:underline">
                                        Người dùng #{{ $log->auditable_id }}
                                    </a>
                                @elseif ($log->auditable_type === $questionMorphClass)
                                    <a href="{{ route('admin.questions.edit', $log->auditable_id) }}" class="whitespace-nowrap text-primary hover:underline">
                                        Câu hỏi #{{ $log->auditable_id }}
                                    </a>
                                @else
                                    <span class="whitespace-nowrap">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $log->ip ?? '—' }}</td>
                        <td class="min-w-52 px-4 py-3">
                            <div class="font-label-md text-on-surface">{{ $log->device_name ?? $log->deviceTypeLabel() }}</div>
                            <div class="text-xs text-on-surface-variant">{{ $log->operating_system ?? 'Không rõ hệ điều hành' }}</div>
                            <div class="text-xs text-on-surface-variant">{{ $log->browser ?? 'Không rõ trình duyệt' }}</div>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('admin.audit.show', $log) }}" class="font-label-md text-primary hover:underline">Chi tiết</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">Chưa có bản ghi audit.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.admin>
