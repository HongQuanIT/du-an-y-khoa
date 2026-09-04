<x-layouts.admin title="Tags">
    <x-admin.page-header title="Thẻ" description="Các thẻ phân loại gắn với câu hỏi (ECG, cấp cứu, trọng tâm…).">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('admin.tags.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-label-md font-semibold text-on-primary">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Tạo tag
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'tags'])

    <x-admin.flash />

    <form method="get" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ $filters['q'] }}" placeholder="Tìm theo tên hoặc slug..."
            class="min-w-[240px] flex-1 rounded-lg bg-surface-container-low px-3 py-2 text-sm">
        <button class="rounded-lg border border-outline-variant px-4 py-2 text-sm font-semibold hover:bg-surface-container-low">Tìm</button>
        @if ($filters['q'])
            <a href="{{ route('admin.tags.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-on-surface-variant hover:text-primary">Xóa bộ lọc</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-surface-container-low text-left font-label-sm text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Tên</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3 text-center">Câu hỏi</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tags as $tag)
                    <tr class="border-t border-outline-variant hover:bg-surface-container-lowest/60">
                        <td class="px-4 py-3 font-semibold">{{ $tag->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $tag->slug }}</td>
                        <td class="px-4 py-3">{{ $tag->type ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">{{ $tag->questions_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $tag->status->value === 'active' ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant' }}">
                                {{ $tag->status->value }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.tags.edit', $tag) }}" class="font-semibold text-primary hover:underline">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-on-surface-variant">
                            Chưa có tag.
                            @if ($canCreate)
                                <a href="{{ route('admin.tags.create') }}" class="ml-1 font-semibold text-primary hover:underline">Tạo tag đầu tiên</a>
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
