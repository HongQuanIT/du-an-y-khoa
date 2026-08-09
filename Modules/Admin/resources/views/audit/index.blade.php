<x-layouts.admin title="Audit">
    <x-admin.page-header title="Audit log"
        description="Nhật ký bất biến các thao tác nhạy cảm (chỉ đọc)." />

    <x-admin.flash />

    <form method="get" action="{{ route('admin.audit.index') }}"
        class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-4">
        <div>
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="action">Action</label>
            <input id="action" name="action" value="{{ $filters['action'] }}" type="search" placeholder="admin.user..."
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="actor_id">Actor ID</label>
            <input id="actor_id" name="actor_id" value="{{ $filters['actor_id'] }}" type="number" min="1"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="ip">IP</label>
            <input id="ip" name="ip" value="{{ $filters['ip'] }}" type="search"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary">Lọc</button>
            <a href="{{ route('admin.audit.index') }}" class="rounded-lg px-4 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Thời gian</th>
                    <th class="px-4 py-3">Actor</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Đối tượng</th>
                    <th class="px-4 py-3">IP</th>
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
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-on-surface">{{ $log->action }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            @if ($log->auditable_type)
                                {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $log->ip ?? '—' }}</td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('admin.audit.show', $log) }}" class="font-label-md text-primary hover:underline">Chi tiết</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">Chưa có bản ghi audit.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.admin>
