<x-layouts.admin title="Lớp học">
    <x-admin.page-header title="Lớp học (giám sát)"
        description="Duyệt lớp giảng viên, xem live đang dạy, force-end hoặc lưu trữ khi cần." />

    <x-admin.flash />

    <div class="mb-6 flex justify-end">
        <a href="{{ route('admin.classrooms.create') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Tạo lớp và nội dung
        </a>
    </div>

    @if ($pendingCount > 0)
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-tertiary/30 bg-tertiary/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="font-body-sm text-body-sm text-on-surface">
                <span class="font-semibold">{{ $pendingCount }}</span> lớp đang chờ duyệt trước khi hiển thị cho học viên.
            </p>
            <a href="{{ route('admin.classrooms.index', ['status' => \Modules\Classroom\Enums\ClassroomStatus::PendingApproval->value]) }}"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary hover:opacity-90">
                Xem chờ duyệt
            </a>
        </div>
    @endif

    <form method="get" action="{{ route('admin.classrooms.index') }}"
        class="mb-6 rounded-xl border border-outline-variant bg-surface p-4 shadow-sm">
        <div class="grid grid-cols-1 items-end gap-4 lg:grid-cols-[minmax(280px,2fr)_minmax(180px,1fr)_minmax(180px,1fr)_auto]">
            <div>
                <label class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant" for="q">Tìm kiếm</label>
                <input id="q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tiêu đề hoặc mã join"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
            </div>
            <div>
                <label class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant" for="status">Trạng thái</label>
                <select id="status" name="status"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant" for="purpose">Mục đích</label>
                <select id="purpose" name="purpose"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Tất cả</option>
                    @foreach ($purposes as $purpose)
                        <option value="{{ $purpose->value }}" @selected($filters['purpose'] === $purpose->value)>{{ $purpose->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                    class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">Lọc</button>
                <a href="{{ route('admin.classrooms.index') }}"
                    class="inline-flex h-11 items-center justify-center rounded-lg border border-outline-variant px-4 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
            </div>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Lớp</th>
                    <th class="px-4 py-3">Giảng viên</th>
                    <th class="px-4 py-3">Mục đích</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">TV</th>
                    <th class="px-4 py-3">Trực tiếp</th>
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
                            {{ match ($classroom->meta['content_source'] ?? null) {
                                'questions' => 'Chữa câu hỏi',
                                'exam' => 'Chữa đề thi',
                                'feedback' => 'Chữa từ feedback',
                                default => $classroom->purpose?->label() ?? '—',
                            } }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval)
                                <span class="font-label-sm text-label-sm font-semibold text-tertiary">{{ $classroom->status->label() }}</span>
                            @else
                                <span class="text-on-surface-variant">{{ $classroom->status->label() }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $classroom->active_members_count }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($classroom->liveSession)
                                <div class="space-y-1">
                                    <span class="font-label-sm text-label-sm font-semibold text-error">ĐANG TRỰC TIẾP</span>
                                    <div class="text-xs text-on-surface-variant">
                                        {{ $classroom->liveSession->title }}
                                    </div>
                                    <div class="text-xs text-on-surface-variant">
                                        GV: {{ $classroom->host?->name ?? '—' }}
                                    </div>
                                </div>
                            @else
                                <span class="text-on-surface-variant">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="{{ route('admin.classrooms.show', $classroom) }}"
                                    class="rounded-lg bg-primary px-3 py-1.5 font-label-sm text-label-sm font-semibold text-on-primary hover:opacity-90">
                                    Vào lớp
                                </a>
                                @if ($classroom->liveSession)
                                    <a href="{{ route('admin.classrooms.live', [$classroom, $classroom->liveSession]) }}"
                                        class="rounded-lg bg-error px-3 py-1.5 font-label-sm text-label-sm font-semibold text-white hover:opacity-90">
                                        Xem live
                                    </a>
                                @endif
                                @if ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval)
                                    <form method="post" action="{{ route('admin.classrooms.approve', $classroom) }}">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-lg bg-primary px-3 py-1.5 font-label-sm text-label-sm font-semibold text-on-primary hover:opacity-90">
                                            Duyệt
                                        </button>
                                    </form>
                                    <form method="post" action="{{ route('admin.classrooms.reject', $classroom) }}"
                                        onsubmit="return confirm('Từ chối lớp này?')">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-label-sm text-on-surface hover:bg-surface-container-low">
                                            Từ chối
                                        </button>
                                    </form>
                                @endif
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
