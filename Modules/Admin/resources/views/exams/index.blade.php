<x-layouts.admin title="Quản lý kỳ thi">
    <x-admin.page-header
        title="Kỳ thi"
        description="Quản lý đề thi mô phỏng, số câu và trạng thái xuất bản cho học viên.">
        <x-slot:actions>
            <a href="{{ route('admin.exams.create') }}"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tạo kỳ thi
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <table class="w-full table-auto text-left">
            <thead class="border-b border-outline-variant bg-surface-container-lowest text-label-sm font-semibold text-on-surface-variant">
                <tr>
                    <th class="px-5 py-4">Kỳ thi</th>
                    <th class="px-5 py-4">Số câu</th>
                    <th class="px-5 py-4">Thời gian</th>
                    <th class="px-5 py-4">Trạng thái</th>
                    <th class="px-5 py-4 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                @forelse ($exams as $exam)
                    <tr class="align-top transition-colors hover:bg-surface-container-lowest/70">
                        <td class="px-5 py-4">
                            <div class="flex items-start gap-3">
                                @if ($exam->icon)
                                    <img src="{{ Storage::disk('public')->url($exam->icon) }}" alt="Icon" class="size-11 rounded-xl object-cover">
                                @else
                                    <div class="flex size-11 items-center justify-center rounded-xl bg-primary-container text-on-primary-container">
                                        <span class="material-symbols-outlined text-[22px]">quiz</span>
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-label-md text-on-surface">{{ $exam->title }}</p>
                                    <p class="mt-1 line-clamp-2 font-body-sm text-on-surface-variant">
                                        {{ $exam->description ?: 'Chưa có mô tả.' }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full bg-primary-container px-3 py-1 text-xs font-bold text-on-primary-container">
                                {{ $exam->questions_count }} câu
                            </span>
                        </td>
                        <td class="px-5 py-4 font-body-sm text-on-surface">
                            {{ $exam->duration_minutes }} phút
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $exam->status?->value === 'published' ? 'bg-primary/10 text-primary' : 'bg-surface-container-high text-on-surface-variant' }}">
                                {{ $exam->status?->label() ?? ($exam->is_published ? 'Đã xuất bản' : 'Nháp') }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.exams.edit', $exam) }}"
                                    class="inline-flex items-center rounded-lg border border-outline-variant px-3 py-2 font-label-sm text-on-surface-variant hover:bg-surface-container-low">
                                    Sửa
                                </a>
                                <form action="{{ route('admin.exams.destroy', $exam) }}" method="POST" onsubmit="return confirm('Xoá kỳ thi này?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex items-center rounded-lg border border-outline-variant px-3 py-2 font-label-sm text-error hover:bg-error/5">
                                        Xoá
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center">
                            <p class="font-label-md text-on-surface">Chưa có kỳ thi nào.</p>
                            <p class="mt-1 font-label-sm text-on-surface-variant">Tạo kỳ thi đầu tiên rồi thêm câu hỏi để học viên bắt đầu làm bài.</p>
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
