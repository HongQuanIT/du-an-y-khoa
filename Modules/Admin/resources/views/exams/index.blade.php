<x-layouts.admin title="Bài thi">
    <x-admin.page-header
        title="Bài thi"
        description="Danh sách bài thi do học viên tạo từ ma trận kỳ thi. Admin chỉ giám sát — không tạo đề tại đây.">
    </x-admin.page-header>

    <x-admin.flash />

    <form method="GET" action="{{ route('admin.exams.index') }}" class="mb-4">
        <div class="relative max-w-md">
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
            <input type="search" name="q" value="{{ request('q') }}"
                placeholder="Tìm theo tên bài, học viên, ma trận…"
                class="h-10 w-full rounded-lg border-none bg-surface-container-low py-2 pr-3 pl-10 font-body-sm text-on-surface focus:ring-2 focus:ring-primary">
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <table class="w-full table-auto text-left">
            <thead class="border-b border-outline-variant bg-surface-container-lowest text-label-sm font-semibold text-on-surface-variant">
                <tr>
                    <th class="px-5 py-4">Bài thi</th>
                    <th class="px-5 py-4">Học viên</th>
                    <th class="px-5 py-4">Kỳ thi (ma trận)</th>
                    <th class="px-5 py-4">Số câu</th>
                    <th class="px-5 py-4">Thời gian</th>
                    <th class="px-5 py-4">Tạo lúc</th>
                    <th class="px-5 py-4 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                @forelse ($exams as $exam)
                    <tr class="align-top transition-colors hover:bg-surface-container-lowest/70">
                        <td class="px-5 py-4">
                            <p class="font-label-md text-on-surface">{{ $exam->title }}</p>
                            <p class="mt-1 line-clamp-1 font-body-sm text-on-surface-variant">
                                {{ $exam->description ?: '—' }}
                            </p>
                        </td>
                        <td class="px-5 py-4">
                            @if ($exam->user)
                                <p class="font-label-md text-on-surface">{{ $exam->user->name }}</p>
                                <p class="mt-0.5 font-label-sm text-on-surface-variant">{{ $exam->user->email }}</p>
                            @else
                                <span class="font-body-sm text-on-surface-variant">Hệ thống / cũ</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if ($exam->blueprint)
                                <p class="font-label-md text-on-surface">{{ $exam->blueprint->name }}</p>
                                @if ($exam->blueprint->code)
                                    <p class="mt-0.5 font-mono text-[11px] text-on-surface-variant">{{ $exam->blueprint->code }}</p>
                                @endif
                            @else
                                <span class="font-body-sm text-on-surface-variant">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full bg-primary-container px-3 py-1 text-xs font-bold text-on-primary-container">
                                {{ $exam->questions_count }} câu
                            </span>
                        </td>
                        <td class="px-5 py-4 font-body-sm text-on-surface">
                            {{ $exam->duration_minutes }} phút
                        </td>
                        <td class="px-5 py-4 font-body-sm text-on-surface-variant">
                            {{ $exam->created_at?->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.exams.show'))
                                    <a href="{{ route('admin.exams.show', $exam) }}"
                                        class="inline-flex items-center rounded-lg border border-outline-variant px-3 py-2 font-label-sm text-on-surface-variant hover:bg-surface-container-low">
                                        Xem
                                    </a>
                                @endif
                                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.exams.destroy'))
                                    <form action="{{ route('admin.exams.destroy', $exam) }}" method="POST" onsubmit="return confirm('Xoá bài thi này?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center rounded-lg border border-outline-variant px-3 py-2 font-label-sm text-error hover:bg-error/5">
                                            Xoá
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center">
                            <p class="font-label-md text-on-surface">Chưa có bài thi nào.</p>
                            <p class="mt-1 font-label-sm text-on-surface-variant">
                                Học viên tạo bài thi tại portal khi chọn kỳ thi (ma trận).
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $exams->links() }}
    </div>
</x-layouts.admin>
