@php
    $type = $type ?? 'text';
    $rows = $rows ?? 3;
    $required = $required ?? false;
    $dotKey = trim(preg_replace('/[\[\]]+/', '.', $name), '.');
    $fieldId = str_replace(['.', '[', ']'], '_', $dotKey);
    $inputValue = old($dotKey, $value);
    $inputClass = 'block w-full min-w-0 max-w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm text-on-surface focus:ring-2 focus:ring-primary';
@endphp

<div class="min-w-0">
    <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="{{ $fieldId }}">
        {{ $label }}@if ($required)<span class="text-error"> *</span>@endif
    </label>
    @if ($type === 'textarea')
        <textarea id="{{ $fieldId }}" name="{{ $name }}" @required($required) rows="{{ $rows }}"
            class="{{ $inputClass }} resize-y">{{ $inputValue }}</textarea>
    @else
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" @required($required)
            value="{{ $inputValue }}"
            class="{{ $inputClass }}">
    @endif
    @if ($hint ?? null)
        <p class="mt-1 font-label-sm text-on-surface-variant">{{ $hint }}</p>
    @endif
    @error($dotKey)
        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
    @enderror
</div>
