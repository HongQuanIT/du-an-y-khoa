{{-- Flag chooser: icon + short outcome. Expects $flags; Alpine `flag` model. --}}
@php
    $checkedValue = $checkedValue ?? null;
@endphp
<div class="mb-4 grid grid-cols-2 gap-3" role="radiogroup" aria-label="Kết quả gắn cờ">
    @foreach ($flags as $option)
        <label
            class="relative flex cursor-pointer flex-col items-center gap-2 rounded-2xl border px-3 py-4 transition-colors"
            :class="flag === '{{ $option->value }}'
                ? '{{ $option->chipClasses(true) }}'
                : '{{ $option->chipClasses(false) }}'">
            <input type="radio" name="flag" value="{{ $option->value }}" required class="sr-only"
                x-model="flag"
                @checked(old('flag', $checkedValue) === $option->value)>
            <span class="material-symbols-outlined text-[32px] leading-none"
                :class="flag === '{{ $option->value }}'
                    ? '{{ $option->iconClasses(true) }}'
                    : '{{ $option->iconClasses(false) }}'"
                aria-hidden="true"
                style="font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 32;">{{ $option->icon() }}</span>
            <span class="text-sm font-semibold tracking-tight">{{ $option->shortLabel() }}</span>
        </label>
    @endforeach
</div>
