<x-layouts.admin title="Thư viện Media">
    <x-admin.page-header title="Thư viện Media"
        description="Ảnh/video local hoặc URL CDN. Dùng lại cho CMS, bài viết và câu hỏi.">
        @if ($canManage)
            <x-slot:actions>
                <button type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface hover:bg-surface-container-low"
                    @click="window.dispatchEvent(new CustomEvent('media-picker:open', { detail: { mode: 'url', accept: 'image' } }))">
                    <span class="material-symbols-outlined text-[18px] leading-none">link</span>
                    URL / CDN
                </button>
                <button type="button"
                    class="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary hover:opacity-90"
                    @click="window.dispatchEvent(new CustomEvent('media-picker:open', { detail: { mode: 'upload', accept: 'all' } }))">
                    <span class="material-symbols-outlined text-[18px] leading-none">upload</span>
                    Tải lên
                </button>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    <x-admin.flash />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Tổng media" :value="number_format($stats['total'])" hint="Ảnh + video" icon="perm_media" />
        <x-admin.kpi-card label="Ảnh" :value="number_format($stats['images'])" hint="Biến thể thumb / webp" icon="image" />
        <x-admin.kpi-card label="Video" :value="number_format($stats['videos'])" hint="Lưu local, chưa HLS" icon="movie" />
    </div>

    <form method="get" action="{{ route('admin.media.index') }}" class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="q">Tìm kiếm</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}"
                placeholder="Tên file, alt…"
                class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
        </div>
        <div class="sm:w-40">
            <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="type">Loại</label>
            <select id="type" name="type"
                class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:w-40">
            <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="status">Trạng thái</label>
            <select id="status" name="status"
                class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit"
            class="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-on-surface hover:bg-surface-container-low">Lọc</button>
    </form>

    @if ($items->isEmpty())
        <div class="rounded-xl border border-dashed border-outline-variant bg-surface px-6 py-16 text-center">
            <span class="material-symbols-outlined text-[40px] text-on-surface-variant">photo_library</span>
            <p class="mt-3 font-label-md text-on-surface">Chưa có media</p>
            <p class="mt-1 font-body-sm text-on-surface-variant">Tải file lên hoặc dán URL ảnh CDN / ngoài.</p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            @foreach ($items as $item)
                <a href="{{ route('admin.media.show', $item) }}"
                    class="group overflow-hidden rounded-xl border border-outline-variant bg-surface hover:border-primary">
                    <div class="relative aspect-square bg-surface-container-low">
                        @if ($item->type === \Modules\Media\Support\Enums\MediaType::Image && $item->thumbUrl())
                            <img src="{{ $item->thumbUrl() }}" alt="{{ $item->alt }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-[36px]">{{ $item->type === \Modules\Media\Support\Enums\MediaType::Video ? 'movie' : 'draft' }}</span>
                            </div>
                        @endif
                        <span class="absolute left-2 top-2 rounded-full bg-surface/90 px-2 py-0.5 font-label-sm text-on-surface">
                            {{ $item->isExternal() ? 'CDN' : $item->type?->label() }}
                        </span>
                    </div>
                    <div class="space-y-1 p-2.5">
                        <p class="truncate font-label-sm text-on-surface">{{ $item->original_name ?: $item->uuid }}</p>
                        <p class="font-label-sm text-on-surface-variant">{{ $item->status?->label() }}</p>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $items->links() }}
        </div>
    @endif
</x-layouts.admin>
