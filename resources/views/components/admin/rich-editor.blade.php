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
    $current = old($name, $value) ?? '';
@endphp

<div class="space-y-1.5"
    x-data="richEditor(@js($current), @js($uploadUrl))">
    <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="{{ $name }}-editor">
        {{ $label }}@if ($required) *@endif
    </label>

    <div x-ref="shell" class="rounded-xl border border-outline-variant bg-surface admin-rich-editor">
        <div data-editor-toolbar class="admin-rich-toolbar"></div>
        <div x-ref="surface" id="{{ $name }}-editor" data-editor-surface
            data-placeholder="{{ $placeholder }}"
            class="admin-rich-surface min-h-[160px] bg-surface font-body-sm text-body-sm text-on-surface"
            contenteditable="true">{!! \App\Support\Html\SafeHtml::fromEditor($current) ?: '' !!}</div>
    </div>

    <input type="hidden" name="{{ $name }}" x-ref="input" value="{{ $current }}">
</div>
