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
        menuStyle: '',
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
        toggleMenu() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.positionMenu());
            }
        },
        positionMenu() {
            const trigger = this.$refs.trigger;
            if (!trigger) return;
            const rect = trigger.getBoundingClientRect();
            const width = Math.max(rect.width, 220);
            this.menuStyle = 'top:' + (rect.bottom + 6) + 'px;left:' + rect.left + 'px;width:' + width + 'px;';
        },
    }"
    @keydown.escape.window="open = false"
    @question-filters-reset.window="selected = []"
    @user-filters-reset.window="selected = []"
    @classroom-filters-reset.window="selected = []"
    @question-feedback-filters-reset.window="selected = []"
    @subscription-filters-reset.window="selected = []"
    @resize.window="open && positionMenu()"
    @scroll.window="open && positionMenu()"
>
    <label for="{{ $name }}-filter-trigger"
        class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant">{{ $label }}</label>

    <template x-for="id in selected" :key="'hidden-' + name + '-' + id">
        <input type="hidden" :name="name + '[]'" :value="id">
    </template>

    <button type="button" id="{{ $name }}-filter-trigger"
        x-ref="trigger"
        class="flex h-11 w-full items-center gap-2 rounded-lg border border-outline-variant bg-surface-container-low px-3 text-left font-body-sm text-on-surface outline-none"
        @click="toggleMenu()"
        :aria-expanded="open" aria-haspopup="listbox" aria-label="{{ $label }}">
        <span class="min-w-0 flex-1 truncate" x-text="triggerLabel"></span>
        <span class="material-symbols-outlined text-[18px] text-on-surface-variant" aria-hidden="true">expand_more</span>
    </button>

    <template x-teleport="body">
        <ul x-show="open" x-cloak x-ref="menu" :style="menuStyle"
            class="fixed z-[100] max-h-64 overflow-y-auto rounded-lg border border-outline-variant bg-surface py-1 shadow-xl"
            role="listbox" aria-multiselectable="true" @click.outside="open = false">
            <template x-for="option in options" :key="option.id">
                <li>
                    <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 transition-colors hover:bg-surface-container-low"
                        :class="selected.includes(option.id) ? 'bg-primary/10 font-medium text-primary' : 'text-on-surface'">
                        <input type="checkbox"
                            class="size-4 rounded border-outline-variant text-primary focus:ring-primary"
                            :checked="selected.includes(option.id)"
                            @change="toggle(option.id)">
                        <span class="font-body-sm text-on-surface" x-text="option.label"></span>
                    </label>
                </li>
            </template>
        </ul>
    </template>
</div>
