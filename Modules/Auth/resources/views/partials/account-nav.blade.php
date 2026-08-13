@php
    $active = $active ?? 'career';

    $profileRoute = fn (?string $tab = null): string => $tab === null || $tab === 'career'
        ? route('profile.show')
        : route('profile.show', ['tab' => $tab]);

    $groups = [
        'Hồ sơ' => [
            'career' => ['label' => 'Hồ sơ cá nhân', 'icon' => 'person', 'href' => $profileRoute()],
        ],
        'Tài khoản' => [
            'contact' => ['label' => 'Liên hệ', 'icon' => 'mail', 'href' => $profileRoute('contact')],
            'security' => ['label' => 'Bảo mật', 'icon' => 'lock', 'href' => $profileRoute('security')],
            'notifications' => ['label' => 'Thông báo', 'icon' => 'notifications', 'href' => $profileRoute('notifications')],
        ],
        'Thanh toán' => [
            'membership' => ['label' => 'Gói & giấy phép', 'icon' => 'workspace_premium', 'href' => $profileRoute('membership')],
            'invoices' => ['label' => 'Hóa đơn', 'icon' => 'receipt_long', 'href' => $profileRoute('invoices')],
            'redeem' => ['label' => 'Đổi mã', 'icon' => 'confirmation_number', 'href' => $profileRoute('redeem')],
        ],
        'Khác' => [
            'org-license' => ['label' => 'Giấy phép tổ chức', 'icon' => 'account_balance', 'href' => $profileRoute('org-license')],
            'notes' => ['label' => 'Ghi chú cá nhân', 'icon' => 'sticky_note_2', 'href' => $profileRoute('notes')],
        ],
    ];

    $allItems = collect($groups)->flatMap(fn (array $items): array => $items);
@endphp

{{-- Mobile: horizontal scroll --}}
<nav class="lg:hidden" aria-label="Tài khoản">
    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
        @foreach ($allItems as $key => $item)
            <a href="{{ $item['href'] }}"
                @class([
                    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 font-label-sm text-label-sm transition-colors',
                    'border-primary bg-primary/10 font-semibold text-primary' => $active === $key,
                    'border-outline-variant bg-surface text-on-surface-variant hover:border-primary/30 hover:text-on-surface' => $active !== $key,
                ])>
                <span class="material-symbols-outlined text-[16px]">{{ $item['icon'] }}</span>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>

{{-- Desktop: vertical sidebar --}}
<nav class="hidden space-y-6 lg:block" aria-label="Tài khoản">
    @foreach ($groups as $groupLabel => $items)
        <div>
            <p class="mb-2 px-3 font-label-sm text-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">
                {{ $groupLabel }}
            </p>
            <ul class="space-y-0.5">
                @foreach ($items as $key => $item)
                    <li>
                        <a href="{{ $item['href'] }}"
                            @class([
                                'flex items-center gap-2.5 rounded-lg px-3 py-2.5 font-label-md text-label-md transition-colors',
                                'bg-primary/10 font-semibold text-primary' => $active === $key,
                                'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface' => $active !== $key,
                            ])>
                            <span @class([
                                'material-symbols-outlined text-[20px]',
                                'text-primary' => $active === $key,
                            ])>{{ $item['icon'] }}</span>
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
