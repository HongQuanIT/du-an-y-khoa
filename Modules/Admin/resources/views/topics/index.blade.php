<x-layouts.admin title="Quản lý chủ đề">
    <x-admin.page-header title="Quản lý chủ đề"
        description="Quản lý các chủ đề dùng trong ngân hàng câu hỏi.">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('admin.topics.create') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:opacity-90">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Thêm chủ đề
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Tổng chủ đề" :value="number_format($stats['total'])" hint="Toàn bộ taxonomy" icon="account_tree" />
        <x-admin.kpi-card label="Đang sử dụng" :value="number_format($stats['used'])" hint="Đã gắn với câu hỏi" icon="check_circle" />
        <x-admin.kpi-card label="Chưa sử dụng" :value="number_format($stats['unused'])" hint="Có thể chỉnh sửa hoặc xóa" icon="inventory_2" />
    </div>

    <form method="get" action="{{ route('admin.topics.index') }}"
        class="mb-6 grid grid-cols-1 items-end gap-3 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-12">
        <div class="md:col-span-11">
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="q">Tìm kiếm</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tên hoặc slug chủ đề"
                class="h-11 w-full rounded-lg border-none bg-surface-container-low px-3 font-body-sm focus:ring-2 focus:ring-primary">
        </div>
        <div class="flex gap-2 md:col-span-1">
            <button type="submit" class="inline-flex h-11 flex-1 items-center justify-center rounded-lg bg-primary px-3 font-label-md text-on-primary">Lọc</button>
        </div>
        <div class="md:col-span-12">
            <a href="{{ route('admin.topics.index') }}" class="font-label-sm text-on-surface-variant hover:text-primary">Xóa bộ lọc</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Tên chủ đề</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3 text-center">Câu hỏi</th>
                    <th class="px-4 py-3 text-center">Thứ tự</th>
                    <th class="px-4 py-3 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/60">
                @forelse ($topics as $topic)
                    <tr class="hover:bg-surface-container-lowest">
                        <td class="px-4 py-3">
                            <p class="font-label-md font-semibold text-on-surface">{{ $topic->name }}</p>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-on-surface-variant">{{ $topic->slug }}</td>
                        <td class="px-4 py-3 text-center text-on-surface-variant">{{ number_format($topic->questions_many_count) }}</td>
                        <td class="px-4 py-3 text-center text-on-surface-variant">{{ number_format($topic->order) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.topics.edit', $topic) }}" class="font-label-md text-primary hover:underline">
                                {{ $canUpdate ? 'Sửa' : 'Xem' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-on-surface-variant">Không tìm thấy chủ đề phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
