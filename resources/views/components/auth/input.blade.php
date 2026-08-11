@props(['name', 'label', 'type' => 'text', 'value' => null])

<div class="space-y-1.5">
    <label class="text-label-md font-label-md text-on-surface-variant" for="{{ $name }}">{{ $label }}</label>
    <input
        {{ $attributes->class([
                'w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all font-body-sm',
                'border-error' => $errors->has($name),
                'border-border' => !$errors->has($name),
            ])->merge([
                'id' => $name,
                'name' => $name,
                'type' => $type,
                'value' => old($name, $value),
            ]) }}>
</div>
