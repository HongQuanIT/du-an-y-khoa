<x-layouts.admin title="Thẻ">
    <x-admin.page-header title="Thẻ" description="Các thẻ phân loại gắn với câu hỏi (ECG, cấp cứu, trọng tâm…).">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('admin.tags.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-label-md font-semibold text-on-primary">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Tạo thẻ
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'tags'])

    <x-admin.flash />

    <form method="get" role="search" aria-label="Lọc danh sách thẻ"
        class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
        <div>
            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="tag-search">Tìm kiếm</label>
            <input id="tag-search" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tên hoặc đường dẫn định danh"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>
        <button class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 font-label-md font-medium text-on-primary hover:opacity-90">Lọc</button>
        @if ($filters['q'])
            <a href="{{ route('admin.tags.index') }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-outline-variant px-4 font-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <caption class="sr-only">Danh sách thẻ dùng để phân loại câu hỏi</caption>
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Tên</th>
                    <th class="px-4 py-3">Đường dẫn định danh</th>
                    <th class="px-4 py-3">Loại</th>
                    <th class="px-4 py-3 text-center">Câu hỏi</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tags as $tag)
                    <tr class="border-b border-outline-variant/60 last:border-0 hover:bg-surface-container-low">
                        <td class="px-4 py-3 font-label-md font-medium text-on-surface">{{ $tag->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $tag->slug }}</td>
                        <td class="px-4 py-3">{{ $tag->type ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">{{ $tag->questions_count }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full border border-outline-variant px-2.5 py-1 font-label-sm text-on-surface">
                                {{ $tag->status->value === 'active' ? 'Đang hoạt động' : 'Ngừng sử dụng' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.tags.edit', $tag) }}" class="inline-flex h-9 items-center rounded-lg border border-outline-variant px-3 font-label-sm font-medium text-on-surface hover:bg-surface-container-low">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-on-surface-variant">
                            Chưa có thẻ.
                            @if ($canCreate)
                                <a href="{{ route('admin.tags.create') }}" class="ml-1 font-semibold text-primary hover:underline">Tạo thẻ đầu tiên</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($tags->hasPages())
        <div class="mt-4">{{ $tags->links() }}</div>
    @endif
</x-layouts.admin>
