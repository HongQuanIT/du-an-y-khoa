@php
    use Modules\Billing\Support\MoneyFormatter;

    $sourceLabels = [
        'purchase' => 'Mua trực tiếp',
        'redeem' => 'Đổi mã',
        'institution' => 'Giấy phép tổ chức',
    ];
@endphp

<x-layouts.app title="Gói đăng ký" description="Gói hiện tại và so sánh quyền lợi.">
    <div class="mx-auto max-w-4xl px-margin-mobile py-8 md:px-gutter md:py-12">
        <div class="mb-8">
            <h1 class="font-headline-md text-headline-md text-on-surface">Gói đăng ký của bạn</h1>
            <p class="mt-1 font-body-md text-body-md text-on-surface-variant">
                Xem gói đang dùng, quyền lợi và các lựa chọn nâng cấp.
            </p>
        </div>

        <section class="mb-8 overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
            <div class="border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6">
                <h2 class="font-title-md text-title-md text-on-surface">Gói hiện tại</h2>
            </div>
            <div class="space-y-5 p-5 md:p-6">
                <div class="flex flex-col gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest/50 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-headline-sm text-headline-sm text-on-surface">
                            {{ $current['plan_name'] }}
                            @if ($current['price_label'])
                                <span class="font-body-md text-on-surface-variant">· {{ $current['price_label'] }}</span>
                            @endif
                        </p>
                        <p class="mt-1 font-body-md text-body-md text-on-surface-variant">{{ $current['description'] }}</p>
                        @if ($current['source'])
                            <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                                Nguồn: {{ $sourceLabels[$current['source']] ?? $current['source'] }}
                            </p>
                        @endif
                        @if ($current['starts_at'])
                            <p class="font-body-sm text-body-sm text-on-surface-variant">
                                Bắt đầu: {{ $current['starts_at']->locale('vi')->isoFormat('D MMMM YYYY') }}
                            </p>
                        @endif
                    </div>
                    <span class="inline-flex w-fit items-center rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm font-semibold text-primary">
                        {{ $current['is_free'] ? 'Miễn phí' : 'Đang hoạt động' }}
                    </span>
                </div>

                @if ($current['entitlement_labels'] !== [])
                    <div>
                        <h3 class="mb-2 font-label-md text-label-md font-semibold text-on-surface">Quyền lợi đang có</h3>
                        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($current['entitlement_labels'] as $label)
                                <li class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface">
                                    <span class="material-symbols-outlined text-[18px] text-primary">verified</span>
                                    {{ $label }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex flex-wrap gap-3 border-t border-outline-variant pt-4">
                    <a href="{{ route('landing.pricing') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                        <span class="material-symbols-outlined text-[18px]">stars</span>
                        {{ $current['is_free'] ? 'Nâng cấp Premium' : 'Xem bảng giá' }}
                    </a>
                    <a href="{{ route('profile.show', ['tab' => 'membership']) }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-outline-variant px-5 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                        Quản lý tài khoản
                    </a>
                    @if ($current['is_free'])
                        <a href="{{ route('profile.show', ['tab' => 'redeem']) }}"
                            class="inline-flex items-center gap-2 rounded-lg border border-outline-variant px-5 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                            Đổi mã kích hoạt
                        </a>
                    @endif
                </div>
            </div>
        </section>

        @if ($free && $premium)
            <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
                <div class="border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6">
                    <h2 class="font-title-md text-title-md text-on-surface">So sánh gói</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left font-body-sm text-body-sm">
                        <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                            <tr>
                                <th class="px-5 py-3">Tính năng</th>
                                <th class="px-5 py-3 text-center">{{ $free->name }}</th>
                                <th class="px-5 py-3 text-center text-primary">{{ $premium->name }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/60">
                            @php
                                $freeFeatures = $free->features ?? [];
                                $premiumFeatures = $premium->features ?? [];
                                $rows = max(count($freeFeatures), count($premiumFeatures));
                            @endphp
                            @for ($i = 0; $i < $rows; $i++)
                                <tr>
                                    <td class="px-5 py-3 text-on-surface-variant">#{{ $i + 1 }}</td>
                                    <td class="px-5 py-3 text-center">{{ $freeFeatures[$i] ?? '—' }}</td>
                                    <td class="px-5 py-3 text-center font-medium text-primary">{{ $premiumFeatures[$i] ?? '—' }}</td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <p class="mt-6 text-center font-body-sm text-body-sm text-on-surface-variant">
            Thanh toán trực tuyến sẽ có trong bản cập nhật tiếp theo. Hiện tại bạn có thể
            <a href="{{ route('profile.show', ['tab' => 'redeem']) }}" class="text-primary hover:underline">đổi mã kích hoạt</a>.
        </p>
    </div>
</x-layouts.app>
