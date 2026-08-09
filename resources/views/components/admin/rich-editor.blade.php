@props([
    'name',
    'label',
    'value' => '',
    'required' => false,
    'placeholder' => '',
    'uploadUrl' => null,
])

@php
    $uploadUrl = $uploadUrl ?? route('admin.editor.images');
@endphp

<div class="space-y-1.5"
    x-data="richEditor(@js(old($name, $value) ?? ''), @js($uploadUrl))">
    <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="{{ $name }}-editor">
        {{ $label }}@if ($required) *@endif
    </label>

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface admin-rich-editor">
        <div x-ref="surface" id="{{ $name }}-editor" data-placeholder="{{ $placeholder }}"
            class="min-h-[160px] bg-surface font-body-sm text-body-sm text-on-surface"></div>
    </div>

    <input type="hidden" name="{{ $name }}" x-ref="input">
</div>
