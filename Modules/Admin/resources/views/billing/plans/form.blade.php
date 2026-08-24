@php
    $featuresText = old('features_text', implode("\n", $plan->features ?? []));
@endphp

<x-layouts.admin title="Sửa gói — {{ $plan->name }}">
    <x-admin.page-header :title="'Sửa gói: '.$plan->name"
        description="Tier {{ $plan->slug }} — quyền lợi và tính năng hiển thị trên bảng giá.">
        <x-slot:actions>
            <a href="{{ route('admin.billing.plans.index') }}"
                class="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">
                Quay lại
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin::billing._nav')

    <x-admin.flash />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <form method="post" action="{{ route('admin.billing.plans.update', $plan) }}"
            class="xl:col-span-2 space-y-6 rounded-xl border border-outline-variant bg-surface p-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block font-label-sm text-on-surface-variant" for="name">Tên gói</label>
                    <input id="name" name="name" type="text" required value="{{ old('name', $plan->name) }}"
                        class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
                </div>
                <div>
                    <label class="mb-1 block font-label-sm text-on-surface-variant" for="sort_order">Thứ tự</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" required
                        value="{{ old('sort_order', $plan->sort_order) }}"
                        class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
                </div>
            </div>

            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="description">Mô tả ngắn</label>
                <input id="description" name="description" type="text" value="{{ old('description', $plan->description) }}"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
            </div>

            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="features_text">Tính năng hiển thị (mỗi dòng một mục)</label>
                <textarea id="features_text" name="features_text" rows="6"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">{{ $featuresText }}</textarea>
            </div>

            <fieldset>
                <legend class="mb-2 font-label-md text-on-surface">Quyền lợi (entitlements)</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($entitlements as $entitlement)
                        <label class="flex items-center gap-2 rounded-lg bg-surface-container-lowest px-3 py-2">
                            <input type="checkbox" name="entitlements[]" value="{{ $entitlement->value }}"
                                @checked(in_array($entitlement->value, old('entitlements', $plan->entitlements ?? []), true))>
                            <span class="font-body-sm">{{ $entitlementLabels[$entitlement->value] ?? $entitlement->value }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))>
                <span class="font-body-sm">Hiển thị trên bảng giá công khai</span>
            </label>

            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-on-primary hover:opacity-90">
                    Lưu gói
                </button>
            </div>
        </form>

        <aside class="space-y-4">
            <div class="rounded-xl border border-outline-variant bg-surface p-5">
                <h2 class="mb-3 font-title-md text-on-surface">Học viên</h2>
                <dl class="space-y-2 font-body-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-on-surface-variant">Đang dùng gói này</dt>
                        <dd class="font-label-md font-semibold text-on-surface">
                            {{ number_format($planStats['learners']) }}
                        </dd>
                    </div>
                    @if ($plan->isFree())
                        <dd class="pt-1 text-on-surface-variant">
                            Gói mặc định — học viên chưa có Premium active.
                        </dd>
                    @else
                        <div class="flex items-center justify-between">
                            <dt class="text-on-surface-variant">Lịch sử kích hoạt</dt>
                            <dd>
                                <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $plan->id, 'status' => 'all']) }}"
                                    class="text-on-surface hover:text-primary hover:underline">
                                    {{ number_format($planStats['history']) }}
                                </a>
                            </dd>
                        </div>
                        <dd class="pt-1">
                            <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $plan->id, 'status' => 'active']) }}"
                                class="font-label-sm text-primary hover:underline">
                                Xem học viên Premium active →
                            </a>
                        </dd>
                    @endif
                </dl>
            </div>

            <div class="rounded-xl border border-outline-variant bg-surface p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-title-md text-on-surface">Mức giá (SKU)</h2>
                    <a href="{{ route('admin.billing.plans.prices.create', $plan) }}"
                        class="rounded-lg bg-primary px-3 py-1.5 font-label-sm text-on-primary hover:opacity-90">
                        Thêm
                    </a>
                </div>
                <ul class="space-y-3">
                    @forelse ($plan->prices as $price)
                        @php
                            $skuStat = $priceStats[$price->id] ?? ['active_users' => 0, 'total' => 0, 'by_source' => []];
                        @endphp
                        <li class="rounded-lg border border-outline-variant/60 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="font-label-md text-on-surface">{{ $price->label }}</p>
                                    <p class="font-mono text-xs text-on-surface-variant">{{ $price->slug }}</p>
                                    <p class="mt-1 font-body-sm text-primary">
                                        {{ number_format($price->price_cents, 0, ',', '.') }}₫
                                        @if ($price->duration_days)
                                            · {{ $price->duration_days }} ngày
                                        @endif
                                    </p>
                                    <p class="mt-2 font-body-sm text-on-surface-variant">
                                        <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $plan->id, 'sku' => $price->id, 'status' => 'active']) }}"
                                            class="font-label-sm text-primary hover:underline">
                                            {{ number_format($skuStat['active_users']) }} học viên
                                        </a>
                                        · {{ number_format($skuStat['total']) }} lịch sử
                                    </p>
                                    @if ($skuStat['by_source'] !== [])
                                        <ul class="mt-1 space-y-0.5 font-body-sm text-on-surface-variant">
                                            @foreach ($skuStat['by_source'] as $source => $count)
                                                <li>
                                                    <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $plan->id, 'sku' => $price->id, 'source' => $source, 'status' => 'active']) }}"
                                                        class="hover:text-primary hover:underline">
                                                        {{ $sourceLabels[$source] ?? $source }}: {{ number_format($count) }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    @if ($price->is_public)
                                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">Public</span>
                                    @else
                                        <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-[10px] text-on-surface-variant">Ẩn</span>
                                    @endif
                                    <a href="{{ route('admin.billing.plan-prices.edit', $price) }}"
                                        class="font-label-sm text-primary hover:underline">Sửa</a>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="font-body-sm text-on-surface-variant">Chưa có mức giá.</li>
                    @endforelse
                </ul>

                @if ($unassignedSku !== null && ($unassignedSku['active_users'] > 0 || $unassignedSku['total'] > 0))
                    <div class="mt-4 rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest p-3">
                        <p class="font-label-md text-on-surface">Chưa gắn SKU</p>
                        <p class="mt-1 font-body-sm text-on-surface-variant">
                            Premium qua đổi mã hoặc giấy phép tổ chức, chưa map SKU cụ thể.
                        </p>
                        <p class="mt-2 font-body-sm">
                            <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $plan->id, 'sku' => 'unassigned', 'status' => 'active']) }}"
                                class="font-label-sm text-primary hover:underline">
                                {{ number_format($unassignedSku['active_users']) }} học viên
                            </a>
                            · {{ number_format($unassignedSku['total']) }} lịch sử
                        </p>
                        @if ($unassignedSku['by_source'] !== [])
                            <ul class="mt-1 space-y-0.5 font-body-sm text-on-surface-variant">
                                @foreach ($unassignedSku['by_source'] as $source => $count)
                                    <li>
                                        <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $plan->id, 'sku' => 'unassigned', 'source' => $source, 'status' => 'active']) }}"
                                            class="hover:text-primary hover:underline">
                                            {{ $sourceLabels[$source] ?? $source }}: {{ number_format($count) }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>
        </aside>
    </div>
</x-layouts.admin>
