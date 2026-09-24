<x-layouts.editor title="Media">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="font-headline-md text-headline-md text-on-surface">Media</h1>
            <p class="mt-1 font-body-sm text-on-surface-variant">Quản lý ảnh, video và tài nguyên nội dung của Editor.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-on-surface">{{ session('status') }}</div>
    @endif

    @can('editor_media.upload')
        <div class="mb-6 grid gap-4 lg:grid-cols-2">
            <form method="post" action="{{ route('editor.media.store') }}" enctype="multipart/form-data" class="space-y-3 rounded-xl border border-outline-variant bg-surface p-4">
                @csrf
                <h2 class="font-label-md font-semibold text-on-surface">Tải tệp lên</h2>
                <input name="file" type="file" required accept="image/*,video/*" class="block w-full text-sm text-on-surface-variant">
                <input name="alt" value="{{ old('alt') }}" placeholder="Mô tả thay thế" class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-sm">
                @error('file')<p class="text-sm text-error">{{ $message }}</p>@enderror
                @error('alt')<p class="text-sm text-error">{{ $message }}</p>@enderror
                <button class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary" type="submit">Tải lên</button>
            </form>

            <form method="post" action="{{ route('editor.media.from-url') }}" class="space-y-3 rounded-xl border border-outline-variant bg-surface p-4">
                @csrf
                <h2 class="font-label-md font-semibold text-on-surface">Thêm từ URL</h2>
                <input name="url" type="url" required value="{{ old('url') }}" placeholder="https://..." class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-sm">
                <input name="alt" value="{{ old('alt') }}" placeholder="Mô tả thay thế" class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-sm">
                <label class="flex items-center gap-2 text-sm text-on-surface"><input type="checkbox" name="import" value="1"> Tải về máy chủ</label>
                @error('url')<p class="text-sm text-error">{{ $message }}</p>@enderror
                <button class="rounded-lg border border-outline-variant px-4 py-2 text-sm font-semibold text-on-surface" type="submit">Thêm URL</button>
            </form>
        </div>
    @endcan

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ([['Tổng tệp', $stats['total'], 'perm_media'], ['Ảnh', $stats['images'], 'image'], ['Video', $stats['videos'], 'movie']] as [$label, $value, $icon])
            <div class="rounded-xl border border-outline-variant bg-surface p-4">
                <div class="flex items-center justify-between"><span class="text-sm text-on-surface-variant">{{ $label }}</span><span class="material-symbols-outlined text-on-surface-variant">{{ $icon }}</span></div>
                <p class="mt-2 text-2xl font-bold text-on-surface">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <form method="get" action="{{ route('editor.media.index') }}" class="mb-6 grid gap-3 sm:grid-cols-[1fr_180px_180px_auto] sm:items-end">
        <label class="text-sm text-on-surface-variant">Tìm kiếm<input name="q" type="search" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-on-surface"></label>
        <label class="text-sm text-on-surface-variant">Loại<select name="type" class="mt-1 block w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-on-surface"><option value="">Tất cả</option>@foreach ($types as $type)<option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
        <label class="text-sm text-on-surface-variant">Trạng thái<select name="status" class="mt-1 block w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-on-surface"><option value="">Tất cả</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
        <button class="rounded-lg border border-outline-variant px-4 py-2 text-sm font-semibold" type="submit">Lọc</button>
    </form>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @forelse ($items as $item)
            <a href="{{ route('editor.media.show', $item) }}" class="overflow-hidden rounded-xl border border-outline-variant bg-surface hover:border-primary">
                <div class="aspect-square bg-surface-container-low">
                    @if ($item->type === \Modules\Media\Support\Enums\MediaType::Image && $item->thumbUrl())
                        <img src="{{ $item->thumbUrl() }}" alt="{{ $item->alt }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center"><span class="material-symbols-outlined text-4xl text-on-surface-variant">movie</span></div>
                    @endif
                </div>
                <div class="p-3"><p class="truncate text-sm font-medium">{{ $item->original_name ?: $item->uuid }}</p><p class="mt-1 text-xs text-on-surface-variant">{{ $item->status?->label() }}</p></div>
            </a>
        @empty
            <p class="col-span-full rounded-xl border border-dashed border-outline-variant p-10 text-center text-on-surface-variant">Chưa có tệp nội dung.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $items->links() }}</div>
</x-layouts.editor>
