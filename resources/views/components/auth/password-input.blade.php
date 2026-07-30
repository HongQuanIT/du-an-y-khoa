@props(['name' => 'password', 'label' => 'Mật khẩu'])

{{-- Password field with its own show/hide state; the slot holds extra hints
     such as the strength meter. --}}
<div class="space-y-1.5" x-data="{ show: false }">
    <label class="text-label-md font-label-md text-on-surface-variant" for="{{ $name }}">{{ $label }}</label>
    <div class="relative">
        <input :type="show ? 'text' : 'password'"
            {{ $attributes->class([
                    'w-full px-4 py-3 pr-12 border rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all font-body-sm',
                    'border-error' => $errors->has($name),
                    'border-border' => !$errors->has($name),
                ])->merge([
                    'id' => $name,
                    'name' => $name,
                    'placeholder' => '••••••••',
                ]) }}>
        <button type="button" @click="show = !show"
            class="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary">
            <span class="material-symbols-outlined text-xl"
                x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
        </button>
    </div>

    {{ $slot }}
</div>
