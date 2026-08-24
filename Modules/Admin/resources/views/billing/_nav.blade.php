@php
    $billingNav = [
        [
            'label' => 'Gói & bảng giá',
            'route' => 'admin.billing.plans.index',
            'match' => ['admin.billing.plans.*', 'admin.billing.plan-prices.*'],
        ],
        [
            'label' => 'Lịch sử Premium',
            'route' => 'admin.billing.subscriptions.index',
            'match' => ['admin.billing.subscriptions.*'],
        ],
        [
            'label' => 'Thanh toán',
            'route' => 'admin.billing.payments.index',
            'match' => ['admin.billing.payments.*'],
        ],
        [
            'label' => 'Cổng thanh toán',
            'route' => 'admin.billing.gateways.index',
            'match' => ['admin.billing.gateways.*'],
        ],
    ];
@endphp

<nav class="mb-6 flex flex-wrap gap-2 border-b border-outline-variant pb-3" aria-label="Billing">
    @foreach ($billingNav as $tab)
        @php $active = request()->routeIs($tab['match']); @endphp
        <a href="{{ route($tab['route']) }}"
            @class([
                'rounded-lg px-3 py-1.5 font-label-md transition-colors',
                'bg-primary text-on-primary' => $active,
                'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface' => ! $active,
            ])>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
