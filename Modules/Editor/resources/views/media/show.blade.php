@php($inUse = $media->usages->count())
<x-layouts.editor :title="$media->original_name ?: 'Chi tiết Media'">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div><h1 class="font-headline-md text-headline-md">{{ $media->original_name ?: 'Chi tiết Media' }}</h1><p class="mt-1 text-sm text-on-surface-variant">Thông tin và nơi đang sử dụng tệp.</p></div>
        <a href="{{ route('editor.media.index') }}" class="rounded-lg px-3 py-2 text-sm text-on-surface-variant hover:bg-surface-container-low">← Thư viện</a>
    </div>

    @if (session('status'))<div class="mb-5 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm">{{ session('status') }}</div>@endif

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <div class="aspect-video overflow-hidden rounded-xl border border-outline-variant bg-surface-container-low">
                @if ($media->type === \Modules\Media\Support\Enums\MediaType::Image && $media->publicUrl('lg'))
                    <img src="{{ $media->publicUrl('lg') }}" alt="{{ $media->alt }}" class="size-full object-contain">
                @elseif ($media->publicUrl())
                    <video controls src="{{ $media->publicUrl() }}" class="size-full bg-black object-contain"></video>
                @endif
            </div>
        </div>
        <div class="space-y-5 lg:col-span-2">
            <div class="rounded-xl border border-outline-variant bg-surface p-5 text-sm text-on-surface-variant">
                <p>Loại: <span class="text-on-surface">{{ $media->type?->label() }}</span></p>
                <p class="mt-2">Trạng thái: <span class="text-on-surface">{{ $media->status?->label() }}</span></p>
                <p class="mt-2">Dung lượng: <span class="text-on-surface">{{ number_format(($media->size_bytes ?? 0) / 1024, 1) }} KB</span></p>
                <p class="mt-2">Nơi sử dụng: <span class="text-on-surface">{{ $inUse }}</span></p>
            </div>

            @can('editor_media.update')
                <form method="post" action="{{ route('editor.media.update', $media) }}" class="space-y-3 rounded-xl border border-outline-variant bg-surface p-5">
                    @csrf @method('PUT')
                    <label class="block text-sm text-on-surface-variant">Mô tả thay thế *<input name="alt" required maxlength="255" value="{{ old('alt', $media->alt) }}" class="mt-1 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-on-surface"></label>
                    @error('alt')<p class="text-sm text-error">{{ $message }}</p>@enderror
                    <label class="block text-sm text-on-surface-variant">Chú thích<input name="caption" maxlength="500" value="{{ old('caption', $media->caption) }}" class="mt-1 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-on-surface"></label>
                    <label class="block text-sm text-on-surface-variant">Nguồn<input name="credit" maxlength="255" value="{{ old('credit', $media->credit) }}" class="mt-1 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-on-surface"></label>
                    <input type="hidden" name="is_premium" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_premium" value="1" @checked(old('is_premium', $media->is_premium))> Cao cấp</label>
                    <button class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary" type="submit">Lưu</button>
                </form>
            @endcan

            @can('editor_media.delete')
                <form method="post" action="{{ route('editor.media.destroy', $media) }}" onsubmit="return confirm('Xóa tệp nội dung này?')">
                    @csrf @method('DELETE')
                    <button type="submit" @disabled($inUse > 0) class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error disabled:opacity-50">{{ $inUse > 0 ? 'Đang dùng — không xóa được' : 'Xóa tệp' }}</button>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.editor>
