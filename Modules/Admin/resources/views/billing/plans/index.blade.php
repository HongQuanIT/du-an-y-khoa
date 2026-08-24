@php
    use Modules\Billing\Support\MoneyFormatter;
@endphp

<x-layouts.admin title="Gói & bảng giá">
    <x-admin.page-header title="Gói & bảng giá"
        description="Cấu hình gói Free/Premium, quyền lợi, và các mức giá (SKU) hiển thị trên /pricing.">
    </x-admin.page-header>

    @include('admin::billing._nav')

    <x-admin.flash />

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.kpi-card label="Tổng học viên" :value="number_format($overview['total_students'])"
            hint="Tài khoản role Học viên" icon="school" />
        <x-admin.kpi-card label="Học viên Free" :value="number_format($overview['free_students'])"
            hint="Mặc định khi đăng ký, chưa có Premium active" icon="person" />
        <x-admin.kpi-card label="Học viên Premium" :value="number_format($overview['premium_students'])"
            hint="Đang dùng gói trả phí (1 tháng, 1 năm…)" icon="workspace_premium" />
        <x-admin.kpi-card label="Premium sắp hết hạn" :value="number_format($overview['expiring_premium_students'])"
            hint="Hết hạn trong 30 ngày tới" icon="schedule" />
    </div>

    <section class="mb-8">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-title-md text-on-surface">Cấu hình gói</h2>
                <p class="mt-1 font-body-sm text-on-surface-variant">
                    Sửa tên, quyền lợi (entitlements), tính năng hiển thị và mức giá bán.
                </p>
            </div>
        </div>

        @if ($plans->isEmpty())
            <div class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-10 text-center">
                <span class="material-symbols-outlined mb-3 text-[40px] text-on-surface-variant">sell</span>
                <h3 class="font-title-md text-on-surface">Chưa có gói nào trong hệ thống</h3>
                <p class="mx-auto mt-2 max-w-lg font-body-sm text-on-surface-variant">
                    Database chưa được seed gói Free/Premium. Chạy seeder Billing rồi tải lại trang
                    để cấu hình bảng giá và SKU.
                </p>
                <pre class="mx-auto mt-4 max-w-xl overflow-x-auto rounded-lg bg-surface px-4 py-3 text-left font-mono text-xs text-on-surface">php artisan db:seed --class="Modules\Billing\Database\Seeders\BillingDatabaseSeeder"</pre>
            </div>
        @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($plans as $plan)
                @php
                    $planStat = $stats['plans'][$plan->id] ?? ['learners' => 0, 'history' => 0];
                @endphp
                <article class="rounded-xl border border-outline-variant bg-surface p-5 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-headline-sm text-headline-sm text-on-surface">{{ $plan->name }}</h3>
                                @if ($plan->isFree())
                                    <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Miễn phí</span>
                                @else
                                    <span class="rounded-full bg-primary/10 px-2 py-0.5 font-label-sm text-primary">Trả phí</span>
                                @endif
                                @if ($plan->is_active)
                                    <span class="rounded-full bg-primary/10 px-2 py-0.5 font-label-sm text-primary">Đang bán</span>
                                @else
                                    <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Ẩn</span>
                                @endif
                            </div>
                            <p class="mt-1 font-body-sm text-on-surface-variant">{{ $plan->description ?: 'Chưa có mô tả' }}</p>
                            <p class="mt-1 font-mono text-xs text-on-surface-variant">slug: {{ $plan->slug }}</p>
                        </div>
                    </div>

                    <dl class="mb-4 grid grid-cols-2 gap-3 rounded-lg bg-surface-container-lowest px-4 py-3 font-body-sm">
                        <div>
                            <dt class="text-on-surface-variant">Học viên đang dùng</dt>
                            <dd class="font-title-md text-on-surface">{{ number_format($planStat['learners']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-on-surface-variant">Số mức giá (SKU)</dt>
                            <dd class="font-title-md text-on-surface">{{ number_format($plan->prices_count) }}</dd>
                        </div>
                    </dl>

                    @if ($plan->prices->isNotEmpty())
                        <ul class="mb-4 divide-y divide-outline-variant/50 rounded-lg border border-outline-variant">
                            @foreach ($plan->prices->take(4) as $price)
                                <li class="flex items-center justify-between gap-3 px-3 py-2 font-body-sm">
                                    <span class="text-on-surface">
                                        {{ $price->label }}
                                        <span class="text-on-surface-variant">
                                            · {{ MoneyFormatter::vnd((int) $price->price_cents) }}
                                        </span>
                                    </span>
                                    <a href="{{ route('admin.billing.plan-prices.edit', $price) }}"
                                        class="shrink-0 font-label-sm font-semibold text-primary hover:underline">
                                        Sửa giá
                                    </a>
                                </li>
                            @endforeach
                            @if ($plan->prices->count() > 4)
                                <li class="px-3 py-2 font-label-sm text-on-surface-variant">
                                    +{{ $plan->prices->count() - 4 }} mức giá khác…
                                </li>
                            @endif
                        </ul>
                    @else
                        <p class="mb-4 rounded-lg border border-dashed border-outline-variant px-3 py-3 font-body-sm text-on-surface-variant">
                            Chưa có mức giá. Thêm SKU để bán trên bảng giá công khai.
                        </p>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.billing.plans.edit', $plan) }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:opacity-90">
                            <span class="material-symbols-outlined text-[18px]">tune</span>
                            Cấu hình gói
                        </a>
                        <a href="{{ route('admin.billing.plans.prices.create', $plan) }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-on-surface hover:bg-surface-container-low">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                            Thêm mức giá
                        </a>
                        @if (! $plan->isFree())
                            <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $plan->id, 'status' => 'active']) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2.5 font-label-md text-primary hover:underline">
                                Xem học viên →
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        @endif
    </section>

    @if ($premiumPlan !== null)
        <section class="rounded-xl border border-outline-variant bg-surface">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant px-5 py-4">
                <div>
                    <h2 class="font-title-md text-on-surface">Bảng giá Premium (SKU)</h2>
                    <p class="mt-1 font-body-sm text-on-surface-variant">
                        Sửa giá, thời hạn, badge — hoặc thêm mức giá mới.
                    </p>
                </div>
                <a href="{{ route('admin.billing.plans.prices.create', $premiumPlan) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 font-label-md font-semibold text-on-primary hover:opacity-90">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Thêm SKU
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left font-body-sm">
                    <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                        <tr>
                            <th class="px-5 py-3">SKU</th>
                            <th class="px-5 py-3">Thời hạn</th>
                            <th class="px-5 py-3 text-right">Giá</th>
                            <th class="px-5 py-3 text-right">Học viên</th>
                            <th class="px-5 py-3">Công khai</th>
                            <th class="px-5 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @forelse ($skuBreakdown as $row)
                            @php
                                /** @var \Modules\Billing\Models\PlanPrice $price */
                                $price = $row['price'];
                            @endphp
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-label-md text-on-surface">{{ $price->label }}</p>
                                    <p class="font-mono text-xs text-on-surface-variant">{{ $price->slug }}</p>
                                    @if ($price->badge_label)
                                        <span class="mt-1 inline-block font-label-sm text-primary">{{ $price->badge_label }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-on-surface-variant">
                                    @if ($price->duration_days)
                                        {{ $price->duration_days }} ngày
                                        <span class="text-on-surface-variant/80">({{ $price->billing_type }})</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right font-label-md text-on-surface">
                                    {{ MoneyFormatter::vnd((int) $price->price_cents) }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $premiumPlan->id, 'sku' => $price->id, 'status' => 'active']) }}"
                                        class="font-label-md font-semibold text-primary hover:underline">
                                        {{ number_format($row['active_users']) }}
                                    </a>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($price->is_public)
                                        <span class="rounded-full bg-primary/10 px-2 py-0.5 font-label-sm text-primary">Hiện</span>
                                    @else
                                        <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Ẩn</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.billing.plan-prices.edit', $price) }}"
                                        class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm font-semibold text-on-surface hover:bg-surface-container-low">
                                        <span class="material-symbols-outlined text-[16px]">edit</span>
                                        Sửa
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-on-surface-variant">
                                    Chưa có SKU Premium.
                                    <a href="{{ route('admin.billing.plans.prices.create', $premiumPlan) }}" class="text-primary hover:underline">Thêm mức giá</a>
                                </td>
                            </tr>
                        @endforelse

                        @if ($unassignedSku !== null && ($unassignedSku['active_users'] > 0 || $unassignedSku['total'] > 0))
                            <tr class="bg-surface-container-lowest/60">
                                <td class="px-5 py-3">
                                    <p class="font-label-md text-on-surface">Chưa gắn SKU</p>
                                    <p class="font-body-sm text-on-surface-variant">Đổi mã, giấy phép tổ chức…</p>
                                </td>
                                <td class="px-5 py-3 text-on-surface-variant">—</td>
                                <td class="px-5 py-3 text-right text-on-surface-variant">—</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $premiumPlan->id, 'sku' => 'unassigned', 'status' => 'active']) }}"
                                        class="font-label-md font-semibold text-primary hover:underline">
                                        {{ number_format($unassignedSku['active_users']) }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 text-on-surface-variant">—</td>
                                <td class="px-5 py-3"></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts.admin>
