@props([
    'name',
    'label',
    'placeholder' => 'Tất cả',
    'options' => [],
    'selected' => [],
])

@php
    $selectedIds = array_values(array_map('strval', $selected));
    $optionPayload = collect($options)
        ->map(fn (array $option): array => [
            'id' => (string) $option['id'],
            'label' => (string) $option['label'],
        ])
        ->values()
        ->all();
@endphp

<div
    class="relative"
    x-data="{
        open: false,
        name: @js($name),
        placeholder: @js($placeholder),
        options: @js($optionPayload),
        selected: @js($selectedIds),
        get triggerLabel() {
            if (this.selected.length === 0) {
                return this.placeholder;
            }

            const first = this.options.find((option) => option.id === this.selected[0]);
            if (this.selected.length === 1) {
                return first?.label ?? this.placeholder;
            }

            return (first?.label ?? this.placeholder) + ' +' + (this.selected.length - 1);
        },
        toggle(id) {
            const value = String(id);
            this.selected = this.selected.includes(value)
                ? this.selected.filter((item) => item !== value)
                : [...this.selected, value];
        },
    }"
    @keydown.escape.window="open = false"
>
    <span class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant">{{ $label }}</span>

    <template x-for="id in selected" :key="'hidden-' + name + '-' + id">
        <input type="hidden" :name="name + '[]'" :value="id">
    </template>

    <button type="button" id="{{ $name }}-filter-trigger"
        class="flex h-11 w-full items-center gap-2 rounded-lg border border-outline-variant bg-surface-container-low px-3 text-left font-body-sm text-on-surface outline-none"
        @click="open = !open"
        :aria-expanded="open" aria-haspopup="listbox" aria-label="{{ $label }}">
        <span class="min-w-0 flex-1 truncate" x-text="triggerLabel"></span>
        <span class="material-symbols-outlined text-[18px] text-on-surface-variant" aria-hidden="true">expand_more</span>
    </button>

    <ul x-show="open" x-cloak
        class="absolute z-40 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-outline-variant bg-surface py-1 shadow-lg"
        role="listbox" aria-multiselectable="true" @click.outside="open = false">
        <template x-for="option in options" :key="option.id">
            <li>
                <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 hover:bg-surface-container-low">
                    <input type="checkbox"
                        class="size-4 rounded border-outline-variant text-primary focus:ring-primary"
                        :checked="selected.includes(option.id)"
                        @change="toggle(option.id)">
                    <span class="font-body-sm text-on-surface" x-text="option.label"></span>
                </label>
            </li>
        </template>
    </ul>
</div>
