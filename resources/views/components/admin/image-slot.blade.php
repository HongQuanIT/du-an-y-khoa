@props([
    'label',
    'mediaIdName',
    'urlName',
    'altName' => null,
    'mediaId' => null,
    'url' => '',
    'alt' => '',
    'aspect' => '16/9',
    'required' => false,
    'hint' => null,
    'accept' => 'image',
])

@php
    $mediaId = old(str_replace(['[', ']'], ['.', ''], $mediaIdName), $mediaId);
    $url = old(str_replace(['[', ']'], ['.', ''], $urlName), $url);
    $altValue = $altName ? old(str_replace(['[', ']'], ['.', ''], $altName), $alt) : $alt;
    $dotUrl = trim(preg_replace('/[\[\]]+/', '.', $urlName), '.');
    $dotAlt = $altName ? trim(preg_replace('/[\[\]]+/', '.', $altName), '.') : null;
@endphp

<div class="min-w-0"
    x-data="mediaImageSlot({
        mediaId: @js($mediaId),
        url: @js($url),
        alt: @js($altValue),
        accept: @js($accept),
    })">
    <p class="mb-1.5 font-label-sm text-label-sm text-on-surface-variant">
        {{ $label }}@if ($required)<span class="text-error"> *</span>@endif
    </p>

    <input type="hidden" name="{{ $mediaIdName }}" :value="mediaId || ''">
    <input type="hidden" name="{{ $urlName }}" :value="url || ''">

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest"
        :class="dragging ? 'border-primary ring-2 ring-primary/20' : ''"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop($event)">
        <div class="relative bg-surface-container-low" style="aspect-ratio: {{ $aspect }}">
            <template x-if="url">
                <div class="absolute inset-0">
                    <img x-show="accept === 'image'" :src="url" :alt="alt || ''" class="size-full object-cover">
                    <video x-show="accept === 'video'" class="size-full object-cover" :src="url" muted></video>
                    <div class="absolute inset-x-0 bottom-0 flex gap-2 bg-gradient-to-t from-black/50 to-transparent p-2">
                        <button type="button" class="rounded-md bg-white/90 px-2 py-1 font-label-sm text-on-surface" @click="openPicker()">Thay</button>
                        <button type="button" class="rounded-md bg-white/90 px-2 py-1 font-label-sm text-on-surface" @click="clear()">Xóa</button>
                    </div>
                </div>
            </template>
            <template x-if="!url">
                <div class="flex size-full flex-col items-center justify-center gap-2 px-4 text-center">
                    <span class="material-symbols-outlined text-[28px] text-on-surface-variant">add_photo_alternate</span>
                    <p class="font-body-sm text-on-surface-variant">Kéo thả, dán, hoặc</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <button type="button" class="rounded-lg bg-primary px-3 py-1.5 font-label-sm text-on-primary" @click="openPicker('upload')">Tải lên</button>
                        <button type="button" class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-on-surface" @click="openPicker('library')">Thư viện</button>
                        <button type="button" class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-on-surface" @click="openPicker('url')">URL / CDN</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    @if ($altName)
        <div class="mt-3">
            <label class="mb-1.5 block font-label-sm text-on-surface-variant">Mô tả ảnh (alt)@if ($required)<span class="text-error"> *</span>@endif</label>
            <input type="text" name="{{ $altName }}" x-model="alt" maxlength="255" @required($required)
                class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
            @if ($dotAlt)
                @error($dotAlt)
                    <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                @enderror
            @endif
        </div>
    @endif

    @if ($hint)
        <p class="mt-1 font-label-sm text-on-surface-variant">{{ $hint }}</p>
    @endif
    @error($dotUrl)
        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
    @enderror
</div>
