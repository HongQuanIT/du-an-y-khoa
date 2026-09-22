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
            'tone' => isset($option['tone']) ? (string) $option['tone'] : null,
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
    @payment-filters-reset.window="selected = []"
    @partner-filters-reset.window="selected = []"
    @audit-filters-reset.window="selected = []"
    @learner-catalog-filters-reset.window="selected = []"
    @faq-filters-reset.window="selected = []"
    @banner-filters-reset.window="selected = []"
    @resize.window="open && positionMenu()"
    @scroll.window="open && positionMenu()"
>
    <label for="{{ $name }}-filter-trigger"
        class="mb-1.5 block text-sm font-medium text-on-surface-variant">{{ $label }}</label>

    <template x-for="id in selected" :key="'hidden-' + name + '-' + id">
        <input type="hidden" :name="name + '[]'" :value="id">
    </template>

    <button type="button" id="{{ $name }}-filter-trigger"
        x-ref="trigger"
        class="flex h-11 w-full items-center justify-between rounded-lg border border-outline-variant bg-surface-container-low px-3 text-left text-sm text-on-surface outline-none transition hover:border-outline focus:border-primary focus:ring-1 focus:ring-primary"
        @click="toggleMenu()"
        :aria-expanded="open" aria-haspopup="listbox" aria-label="{{ $label }}">
        <span class="min-w-0 flex-1 truncate" x-text="triggerLabel"></span>
        <div class="flex shrink-0 items-center gap-1">
            <template x-if="selected.length > 0">
                <span class="flex size-5 items-center justify-center rounded-full bg-primary text-xs font-bold text-on-primary" x-text="selected.length"></span>
            </template>
            <span class="material-symbols-outlined text-[20px] text-on-surface-variant transition-transform duration-200" :class="open ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
        </div>
    </button>

    <template x-teleport="body">
        <ul x-show="open" x-cloak x-ref="menu" :style="menuStyle"
            class="fixed z-[100] max-h-72 overflow-y-auto rounded-xl border border-outline-variant bg-surface p-2 shadow-xl"
            role="listbox" aria-multiselectable="true" @click.outside="open = false">
            <li class="mb-1.5 flex items-center justify-between border-b border-outline-variant px-2 pb-1.5 text-xs">
                <span class="font-semibold text-on-surface-variant">Chọn {{ $label }}</span>
                <button type="button" @click="selected = []" class="text-xs text-primary hover:underline" x-show="selected.length > 0">Bỏ chọn</button>
            </li>
            <template x-for="option in options" :key="option.id">
                <li>
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-sm transition-colors hover:bg-surface-container-low"
                        :class="selected.includes(option.id) ? 'bg-primary/10 font-medium text-primary' : 'text-on-surface'">
                        <input type="checkbox"
                            class="size-4 rounded border-outline-variant text-primary focus:ring-primary"
                            :checked="selected.includes(option.id)"
                            @change="toggle(option.id)">
                        <template x-if="option.tone">
                            <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium" :class="option.tone" x-text="option.label"></span>
                        </template>
                        <template x-if="!option.tone">
                            <span class="font-body-sm text-on-surface" x-text="option.label"></span>
                        </template>
                    </label>
                </li>
            </template>
        </ul>
    </template>
</div>
