@php
    $showAlt = $showAlt ?? true;
    $aspect = $aspect ?? '16/9';
    $required = $required ?? true;
    $hint = $hint ?? 'Tải lên, chọn từ thư viện, hoặc dán URL CDN / ảnh ngoài.';
    $value = $value ?? [];
@endphp

<x-admin.image-slot
    :label="$label"
    :media-id-name="$prefix.'[image_media_id]'"
    :url-name="$prefix.'[image_url]'"
    :alt-name="$showAlt ? $prefix.'[image_alt]' : null"
    :media-id="$value['image_media_id'] ?? null"
    :url="$value['image_url'] ?? ''"
    :alt="$value['image_alt'] ?? ''"
    :aspect="$aspect"
    :required="$required"
    :hint="$hint"
/>
