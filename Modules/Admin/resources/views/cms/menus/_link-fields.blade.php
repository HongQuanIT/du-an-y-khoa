@php
    /** @var array<string, string> $routeOptions */
    $indexExpr = $indexExpr ?? 'index';
    $linkExpr = $linkExpr ?? 'link';
    $listExpr = $listExpr ?? 'items.links';
    $namePrefixExpr = $namePrefixExpr ?? (isset($prefix) ? "'".addslashes($prefix)."'" : "''");
@endphp

<div class="grid grid-cols-1 gap-3 lg:grid-cols-12 lg:items-end">
    <div class="min-w-0 lg:col-span-3">
        <label class="mb-1.5 block font-label-sm text-on-surface-variant">Nhãn</label>
        <input type="text" required maxlength="120" x-model="{{ $linkExpr }}.label"
            :name="{{ $namePrefixExpr }}+'['+{{ $indexExpr }}+'][label]'"
            class="block w-full rounded-lg border-none bg-surface px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
    </div>

    <div class="min-w-0 lg:col-span-2">
        <label class="mb-1.5 block font-label-sm text-on-surface-variant">Loại</label>
        <select x-model="{{ $linkExpr }}.type"
            :name="{{ $namePrefixExpr }}+'['+{{ $indexExpr }}+'][type]'"
            class="block w-full rounded-lg border-none bg-surface px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
            <option value="route">Route nội bộ</option>
            <option value="url">URL / path</option>
        </select>
    </div>

    <div class="min-w-0 lg:col-span-4">
        <label class="mb-1.5 block font-label-sm text-on-surface-variant">Đích đến</label>
        <select x-show="{{ $linkExpr }}.type === 'route'" x-cloak x-model="{{ $linkExpr }}.value"
            :name="{{ $linkExpr }}.type === 'route' ? ({{ $namePrefixExpr }}+'['+{{ $indexExpr }}+'][value]') : null"
            class="block w-full rounded-lg border-none bg-surface px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
            @foreach ($routeOptions as $route => $label)
                <option value="{{ $route }}">{{ $label }}</option>
            @endforeach
        </select>
        <input type="text" x-show="{{ $linkExpr }}.type === 'url'" x-cloak x-model="{{ $linkExpr }}.value"
            :name="{{ $linkExpr }}.type === 'url' ? ({{ $namePrefixExpr }}+'['+{{ $indexExpr }}+'][value]') : null"
            placeholder="https://… hoặc /path hoặc #"
            class="block w-full rounded-lg border-none bg-surface px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary"
            maxlength="2048">
    </div>

    <div class="flex flex-wrap items-center gap-3 lg:col-span-3 lg:justify-end lg:pb-1">
        <label class="inline-flex items-center gap-2 font-label-sm text-on-surface">
            <input type="checkbox" value="1" x-model="{{ $linkExpr }}.enabled"
                :name="{{ $namePrefixExpr }}+'['+{{ $indexExpr }}+'][enabled]'"
                class="rounded border-outline-variant text-primary focus:ring-primary">
            Bật
        </label>
        <button type="button" class="rounded-lg px-2 py-1 font-label-sm text-on-surface-variant hover:bg-surface"
            @click="moveItem({{ $listExpr }}, {{ $indexExpr }}, -1)" :disabled="{{ $indexExpr }} === 0">↑</button>
        <button type="button" class="rounded-lg px-2 py-1 font-label-sm text-on-surface-variant hover:bg-surface"
            @click="moveItem({{ $listExpr }}, {{ $indexExpr }}, 1)" :disabled="{{ $indexExpr }} === {{ $listExpr }}.length - 1">↓</button>
        <button type="button" class="rounded-lg px-2 py-1 font-label-sm text-error hover:bg-error/10"
            @click="removeAt({{ $listExpr }}, {{ $indexExpr }})">Xóa</button>
    </div>
</div>
