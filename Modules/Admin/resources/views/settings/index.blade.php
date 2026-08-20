@php
    $firstGroup = array_key_first($groups);

    $fieldValue = function (string $groupKey, string $fieldKey, array $field) use ($settings): mixed {
        return old("settings.{$groupKey}.{$fieldKey}", $settings[$groupKey][$fieldKey] ?? ($field['default'] ?? null));
    };
@endphp

<x-layouts.admin title="Cài đặt">
    <x-admin.page-header title="Cài đặt hệ thống"
        description="Quản lý cấu hình vận hành tập trung mà không cần sửa code hoặc file môi trường." />

    <x-admin.flash />

    <form method="post" action="{{ route('admin.settings.update') }}" x-data="{ active: '{{ $firstGroup }}' }"
        class="rounded-xl border border-outline-variant bg-surface">
        @csrf

        <div class="border-b border-outline-variant px-4 pt-4">
            <div class="flex gap-2 overflow-x-auto" role="tablist" aria-label="Nhóm cài đặt">
                @foreach ($groups as $groupKey => $group)
                    <button type="button" @click="active = '{{ $groupKey }}'"
                        class="inline-flex shrink-0 items-center gap-2 rounded-t-lg border-b-2 px-4 py-3 font-label-md text-label-md transition-colors"
                        :class="active === '{{ $groupKey }}'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface'"
                        role="tab" :aria-selected="active === '{{ $groupKey }}'">
                        <span class="material-symbols-outlined text-[20px] leading-none">{{ $group['icon'] }}</span>
                        {{ $group['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        @foreach ($groups as $groupKey => $group)
            <section x-show="active === '{{ $groupKey }}'" x-cloak class="p-5" role="tabpanel">
                <div class="mb-6">
                    <h2 class="font-title-md text-title-md text-on-surface">{{ $group['label'] }}</h2>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ $group['description'] }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    @foreach ($group['fields'] as $fieldKey => $field)
                        @php
                            $name = "settings[{$groupKey}][{$fieldKey}]";
                            $dotName = "settings.{$groupKey}.{$fieldKey}";
                            $value = $fieldValue($groupKey, $fieldKey, $field);
                            $isWide = ($field['textarea'] ?? false) || $field['type'] === 'boolean';
                        @endphp

                        <div @class([
                            'rounded-lg border border-outline-variant bg-surface-container-lowest p-4',
                            'lg:col-span-2' => $isWide,
                        ])>
                            @if ($field['type'] === 'boolean')
                                <label class="flex items-start justify-between gap-4">
                                    <span>
                                        <span class="block font-label-lg text-label-lg text-on-surface">{{ $field['label'] }}</span>
                                        <span class="mt-1 block font-body-sm text-body-sm text-on-surface-variant">
                                            {{ $value ? 'Đang bật' : 'Đang tắt' }}
                                        </span>
                                    </span>
                                    <input type="checkbox" name="{{ $name }}" value="1" @checked((bool) $value)
                                        class="mt-1 size-5 rounded border-outline text-primary focus:ring-primary">
                                </label>
                            @elseif (($field['textarea'] ?? false) === true)
                                <label for="{{ $groupKey }}_{{ $fieldKey }}"
                                    class="mb-2 block font-label-md text-label-md text-on-surface">{{ $field['label'] }}</label>
                                <textarea id="{{ $groupKey }}_{{ $fieldKey }}" name="{{ $name }}" rows="4"
                                    class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">{{ $value }}</textarea>
                            @else
                                <label for="{{ $groupKey }}_{{ $fieldKey }}"
                                    class="mb-2 block font-label-md text-label-md text-on-surface">{{ $field['label'] }}</label>
                                <input id="{{ $groupKey }}_{{ $fieldKey }}" name="{{ $name }}"
                                    type="{{ $field['type'] === 'integer' ? 'number' : 'text' }}"
                                    value="{{ $value }}"
                                    @if ($field['type'] === 'integer') min="1" max="500" @endif
                                    class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            @endif

                            @error($dotName)
                                <p class="mt-2 font-body-sm text-body-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex flex-col gap-3 border-t border-outline-variant px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="font-body-sm text-body-sm text-on-surface-variant">Cache settings sẽ được làm mới ngay sau khi lưu.</p>
            <button type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-bold text-on-primary transition-opacity hover:opacity-90">
                <span class="material-symbols-outlined text-[20px] leading-none">save</span>
                Lưu cài đặt
            </button>
        </div>
    </form>
</x-layouts.admin>
