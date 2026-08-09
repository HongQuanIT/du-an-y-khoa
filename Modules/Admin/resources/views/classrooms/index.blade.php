<x-layouts.admin title="Lớp học">
    <x-admin.page-header title="Lớp học (giám sát)"
        description="Xem mọi lớp trên hệ thống. Force-end live hoặc lưu trữ khi cần — không thay workspace giảng viên." />

    <x-admin.flash />

    <form method="get" action="{{ route('admin.classrooms.index') }}"
        class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="q">Tìm kiếm</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tiêu đề hoặc mã join"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
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
        <div>
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="purpose">Mục đích</label>
            <select id="purpose" name="purpose"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($purposes as $purpose)
                    <option value="{{ $purpose->value }}" @selected($filters['purpose'] === $purpose->value)>{{ $purpose->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-4 flex gap-2">
            <button type="submit"
                class="rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary hover:opacity-90">Lọc</button>
            <a href="{{ route('admin.classrooms.index') }}"
                class="rounded-lg px-4 py-2 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Lớp</th>
                    <th class="px-4 py-3">Host</th>
                    <th class="px-4 py-3">Mục đích</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">TV</th>
                    <th class="px-4 py-3">Live</th>
                    <th class="px-4 py-3 text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classrooms as $classroom)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-label-md text-label-md text-on-surface">{{ $classroom->title }}</div>
                            <div class="font-label-sm text-label-sm text-on-surface-variant">
                                {{ $classroom->join_code ?? '—' }} · {{ $classroom->uuid }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $classroom->host?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $classroom->purpose?->label() ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $classroom->status->label() }}
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $classroom->active_members_count }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($classroom->live_sessions_count > 0)
                                <span class="font-label-sm text-label-sm font-semibold text-error">LIVE</span>
                            @else
                                <span class="text-on-surface-variant">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($classroom->live_sessions_count > 0)
                                    <form method="post" action="{{ route('admin.classrooms.force-end', $classroom) }}"
                                        onsubmit="return confirm('Force-end buổi live của lớp này?')">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-label-sm text-on-surface hover:bg-surface-container-low">
                                            Force-end
                                        </button>
                                    </form>
                                @endif
                                @if ($classroom->status !== \Modules\Classroom\Enums\ClassroomStatus::Archived)
                                    <form method="post" action="{{ route('admin.classrooms.archive', $classroom) }}"
                                        onsubmit="return confirm('Lưu trữ lớp này?')">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-label-sm text-on-surface hover:bg-surface-container-low">
                                            Lưu trữ
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">Không có lớp khớp bộ lọc.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $classrooms->links() }}
    </div>
</x-layouts.admin>
