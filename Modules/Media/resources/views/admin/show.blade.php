@php
    use Modules\Admin\Models\CmsPage;
    use Modules\Media\Support\Enums\MediaType;

    $variants = is_array($media->variants) ? $media->variants : [];
    $inUse = $media->usages->count();
@endphp

<x-layouts.admin title="Media — {{ $media->original_name ?: $media->uuid }}">
    <x-admin.page-header :title="$media->original_name ?: 'Chi tiết media'"
        description="Metadata, biến thể local và nơi đang sử dụng.">
        <x-slot:actions>
            <a href="{{ route('admin.media.index') }}"
                class="inline-flex items-center rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Thư viện</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="aspect-video bg-surface-container-low">
                    @if ($media->type === MediaType::Image && $media->publicUrl('lg'))
                        <img src="{{ $media->publicUrl('lg') }}" alt="{{ $media->alt }}" class="size-full object-contain">
                    @elseif ($media->type === MediaType::Video && $media->publicUrl())
                        <video class="size-full bg-black object-contain" controls src="{{ $media->publicUrl() }}"></video>
                    @else
                        <div class="flex size-full items-center justify-center text-on-surface-variant">
                            Không xem trước được
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="border-b border-outline-variant px-5 py-4">
                    <h3 class="font-label-md text-on-surface">Biến thể</h3>
                </div>
                <div class="divide-y divide-outline-variant/70">
                    @forelse ($variants as $name => $variant)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div>
                                <p class="font-label-sm text-on-surface">{{ $name }}</p>
                                <p class="font-body-sm text-on-surface-variant">
                                    {{ $variant['path'] ?? '—' }}
                                    @if (! empty($variant['width']))
                                        · {{ $variant['width'] }}×{{ $variant['height'] ?? '?' }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-4 font-body-sm text-on-surface-variant">Chưa có biến thể.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="border-b border-outline-variant px-5 py-4">
                    <h3 class="font-label-md text-on-surface">Thông tin</h3>
                </div>
                <div class="space-y-3 p-5 font-body-sm text-on-surface-variant">
                    <p>Loại: <span class="text-on-surface">{{ $media->type?->label() }}</span></p>
                    <p>Trạng thái:
                        <span class="text-on-surface">{{ $media->status?->label() }}</span>
                    </p>
                    <p>MIME: <span class="text-on-surface">{{ $media->mime ?: '—' }}</span></p>
                    <p>Dung lượng: <span class="text-on-surface">{{ number_format(($media->size_bytes ?? 0) / 1024, 1) }} KB</span></p>
                    <p>Disk: <span class="text-on-surface">{{ $media->isExternal() ? 'CDN / URL ngoài' : $media->disk }}</span></p>
                    @if ($media->isExternal())
                        <p class="break-all">URL: <a href="{{ $media->path }}" class="text-primary hover:underline" target="_blank" rel="noopener noreferrer">{{ $media->path }}</a></p>
                    @endif
                </div>
            </div>

            @if ($canManage)
                <form method="post" action="{{ route('admin.media.update', $media) }}" class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                    @csrf
                    @method('PUT')
                    <div class="border-b border-outline-variant px-5 py-4">
                        <h3 class="font-label-md text-on-surface">Metadata</h3>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="alt">Alt *</label>
                            <input id="alt" name="alt" type="text" required maxlength="255"
                                value="{{ old('alt', $media->alt) }}"
                                class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                            @error('alt') <p class="mt-1 font-label-sm text-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="caption">Chú thích</label>
                            <input id="caption" name="caption" type="text" maxlength="500"
                                value="{{ old('caption', $media->caption) }}"
                                class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="credit">Nguồn / credit</label>
                            <input id="credit" name="credit" type="text" maxlength="255"
                                value="{{ old('credit', $media->credit) }}"
                                class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                        </div>
                        <label class="flex items-center gap-2 font-label-sm text-on-surface">
                            <input type="hidden" name="is_premium" value="0">
                            <input type="checkbox" name="is_premium" value="1" @checked(old('is_premium', $media->is_premium))>
                            Premium (không public `/storage`)
                        </label>
                        <button type="submit"
                            class="inline-flex rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">Lưu</button>
                    </div>
                </form>
            @endif

            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="border-b border-outline-variant px-5 py-4">
                    <h3 class="font-label-md text-on-surface">Nơi sử dụng</h3>
                </div>
                <div class="p-5">
                    @if ($inUse === 0)
                        <p class="font-body-sm text-on-surface-variant">Chưa gắn vào nội dung nào.</p>
                    @else
                        <ul class="space-y-2 font-body-sm">
                            @foreach ($media->usages as $usage)
                                <li class="text-on-surface">
                                    @if ($usage->usable instanceof CmsPage)
                                        CMS: {{ $usage->usable->title }}
                                    @else
                                        {{ class_basename((string) $usage->usable_type) }} #{{ $usage->usable_id }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($canManage)
                        <form method="post" action="{{ route('admin.media.destroy', $media) }}" class="mt-4"
                            onsubmit="return confirm('Xóa media này? File trên đĩa sẽ bị gỡ.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="inline-flex rounded-lg border border-error/40 px-4 py-2 font-label-md text-error hover:bg-error/10"
                                @disabled($inUse > 0)>
                                {{ $inUse > 0 ? 'Đang dùng — không xóa được' : 'Xóa media' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
