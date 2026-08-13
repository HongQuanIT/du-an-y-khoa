<x-layouts.admin title="Gói & bảng giá">
    <x-admin.page-header title="Gói & bảng giá"
        description="Thống kê học viên theo gói Free (mặc định) và Premium (theo SKU).">
        <x-slot:actions>
            <a href="{{ route('admin.billing.subscriptions.index') }}"
                class="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-on-surface hover:bg-surface-container-low">
                Lịch sử Premium
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.kpi-card label="Tổng học viên" :value="number_format($overview['total_students'])"
            hint="Tài khoản role Học viên" icon="school" />
        <x-admin.kpi-card label="Học viên Free" :value="number_format($overview['free_students'])"
            hint="Mặc định khi đăng ký, chưa có Premium active" icon="person" />
        <x-admin.kpi-card label="Học viên Premium" :value="number_format($overview['premium_students'])"
            hint="Đang dùng gói trả phí (1 tháng, 1 năm…)" icon="workspace_premium" />
        <x-admin.kpi-card label="Premium sắp hết hạn" :value="number_format($overview['expiring_premium_students'])"
            hint="Hết hạn trong 30 ngày tới" icon="schedule" />
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        @foreach ($plans as $plan)
            @php
                $planStat = $stats['plans'][$plan->id] ?? ['learners' => 0, 'history' => 0];
            @endphp
            <div class="rounded-xl border border-outline-variant bg-surface p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-title-md text-on-surface">{{ $plan->name }}</h2>
                            @if ($plan->isFree())
                                <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Mặc định</span>
                            @else
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 font-label-sm text-primary">Trả phí</span>
                            @endif
                            @if ($plan->is_active)
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 font-label-sm text-primary">Đang bán</span>
                            @else
                                <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Ẩn</span>
                            @endif
                        </div>
                        <p class="mt-1 font-body-sm text-on-surface-variant">{{ $plan->description }}</p>
                        <p class="mt-1 font-mono text-xs text-on-surface-variant">{{ $plan->slug }}</p>
                    </div>
                    @if ($canManage)
                        <a href="{{ route('admin.billing.plans.edit', $plan) }}"
                            class="shrink-0 font-label-md text-primary hover:underline">Cấu hình</a>
                    @endif
                </div>

                <div class="flex items-end justify-between gap-4 rounded-lg bg-surface-container-lowest px-4 py-3">
                    <div>
                        <p class="font-label-sm text-on-surface-variant">Học viên đang dùng</p>
                        <p class="font-headline-sm text-on-surface">{{ number_format($planStat['learners']) }}</p>
                    </div>
                    @if (! $plan->isFree())
                        <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $plan->id, 'status' => 'active']) }}"
                            class="font-label-sm text-primary hover:underline">
                            Xem lịch sử →
                        </a>
                    @endif
                </div>

                @if ($plan->isFree())
                    <p class="mt-3 font-body-sm text-on-surface-variant">
                        Học viên mới đăng ký tự động dùng Free — không tạo bản ghi subscription.
                    </p>
                @else
                    <p class="mt-3 font-body-sm text-on-surface-variant">
                        {{ $plan->prices_count }} SKU · {{ number_format($planStat['history']) }} lần kích hoạt Premium (lịch sử)
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    @if ($premiumPlan !== null)
        <section class="rounded-xl border border-outline-variant bg-surface">
            <div class="border-b border-outline-variant px-5 py-4">
                <h2 class="font-title-md text-on-surface">Phân bổ Premium theo SKU</h2>
                <p class="mt-1 font-body-sm text-on-surface-variant">
                    Số học viên đang active trên từng mức giá (1 tháng, 1 năm, 2 năm…).
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left font-body-sm">
                    <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                        <tr>
                            <th class="px-5 py-3">SKU</th>
                            <th class="px-5 py-3">Thời hạn</th>
                            <th class="px-5 py-3 text-right">Giá</th>
                            <th class="px-5 py-3 text-right">Học viên</th>
                            <th class="px-5 py-3">Tỷ lệ</th>
                            <th class="px-5 py-3"></th>
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
                                </td>
                                <td class="px-5 py-3 text-on-surface-variant">
                                    @if ($price->duration_days)
                                        {{ $price->duration_days }} ngày
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right text-on-surface">
                                    {{ number_format($price->price_cents, 0, ',', '.') }}₫
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.billing.subscriptions.index', ['plan' => $premiumPlan->id, 'sku' => $price->id, 'status' => 'active']) }}"
                                        class="font-label-md font-semibold text-primary hover:underline">
                                        {{ number_format($row['active_users']) }}
                                    </a>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex min-w-[120px] items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-container-high">
                                            <div class="h-full rounded-full bg-primary"
                                                style="width: {{ min(100, $row['share_percent']) }}%"></div>
                                        </div>
                                        <span class="w-10 text-right font-label-sm text-on-surface-variant">{{ $row['share_percent'] }}%</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($canManage)
                                        <a href="{{ route('admin.billing.plan-prices.edit', $price) }}"
                                            class="font-label-sm text-primary hover:underline">Sửa SKU</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-on-surface-variant">Chưa có SKU Premium.</td>
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
