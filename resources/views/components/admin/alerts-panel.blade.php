@props([
    'alerts' => [],
    'viewAllHref' => null,
])

@php
    $counts = [
        'critical' => 0,
        'warning' => 0,
        'info' => 0,
        'ok' => 0,
    ];
    foreach ($alerts as $alert) {
        $severity = $alert['severity'] ?? 'info';
        if (isset($counts[$severity])) {
            $counts[$severity]++;
        }
    }
    $attention = $counts['critical'] + $counts['warning'] + $counts['info'];
@endphp

<section {{ $attributes->class(['rounded-xl border border-outline-variant bg-surface p-5']) }}>
    <div class="mb-4 flex items-start justify-between gap-3">
        <div>
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Sức khỏe hệ thống</h3>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                Kiểm tra theo hạng mục ·
                @if ($counts['critical'] > 0)
                    <span class="text-red-700">{{ $counts['critical'] }} lỗi</span> ·
                @endif
                @if ($counts['warning'] > 0)
                    <span class="text-amber-700">{{ $counts['warning'] }} cảnh báo</span> ·
                @endif
                @if ($counts['info'] > 0)
                    <span class="text-primary">{{ $counts['info'] }} thông tin</span> ·
                @endif
                <span class="text-emerald-700">{{ $counts['ok'] }} ổn định</span>
            </p>
        </div>
        @if ($viewAllHref)
            <a href="{{ $viewAllHref }}" class="font-label-sm text-label-sm text-primary hover:underline">Xem tất cả</a>
        @endif
    </div>

    @if (count($alerts) === 0)
        <div class="flex items-center gap-3 rounded-lg bg-surface-container-low px-4 py-3">
            <span class="material-symbols-outlined text-[22px] text-on-surface-variant">info</span>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                Không có hạng mục nào trong phạm vi quyền của bạn để hiển thị trạng thái.
            </p>
        </div>
    @else
        <ul class="max-h-[28rem] space-y-2 overflow-y-auto pe-1">
            @foreach ($alerts as $alert)
                @php
                    $styles = match ($alert['severity']) {
                        'critical' => [
                            'border' => 'border-red-500/30 bg-red-500/5',
                            'icon' => 'error',
                            'iconClass' => 'text-red-600',
                            'badge' => 'bg-red-500/10 text-red-800',
                            'badgeLabel' => 'Lỗi',
                        ],
                        'warning' => [
                            'border' => 'border-amber-500/30 bg-amber-500/5',
                            'icon' => 'warning',
                            'iconClass' => 'text-amber-600',
                            'badge' => 'bg-amber-500/10 text-amber-900',
                            'badgeLabel' => 'Cảnh báo',
                        ],
                        'ok' => [
                            'border' => 'border-emerald-500/25 bg-emerald-500/5',
                            'icon' => 'check_circle',
                            'iconClass' => 'text-emerald-600',
                            'badge' => 'bg-emerald-500/10 text-emerald-800',
                            'badgeLabel' => 'Ổn định',
                        ],
                        default => [
                            'border' => 'border-primary/20 bg-primary/5',
                            'icon' => 'info',
                            'iconClass' => 'text-primary',
                            'badge' => 'bg-primary/10 text-primary',
                            'badgeLabel' => 'Thông tin',
                        ],
                    };
                @endphp
                <li>
                    @if (! empty($alert['href']))
                        <a href="{{ $alert['href'] }}"
                            class="flex items-start gap-3 rounded-lg border px-4 py-3 transition hover:shadow-sm {{ $styles['border'] }}">
                    @else
                        <div class="flex items-start gap-3 rounded-lg border px-4 py-3 {{ $styles['border'] }}">
                    @endif
                        <span class="material-symbols-outlined mt-0.5 text-[20px] {{ $styles['iconClass'] }}">{{ $styles['icon'] }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="mb-1 flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 font-label-sm text-label-sm {{ $styles['badge'] }}">{{ $styles['badgeLabel'] }}</span>
                                <span class="font-label-sm text-on-surface-variant">{{ $alert['category'] ?? 'Hệ thống' }}</span>
                            </span>
                            <span class="block font-label-md text-label-md text-on-surface">{{ $alert['title'] }}</span>
                            <span class="mt-0.5 block font-body-sm text-body-sm text-on-surface-variant">{{ $alert['message'] }}</span>
                        </span>
                        @if (! empty($alert['href']))
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant/60">chevron_right</span>
                        @endif
                    @if (! empty($alert['href']))
                        </a>
                    @else
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($attention === 0 && $counts['ok'] > 0)
            <p class="mt-3 font-label-sm text-emerald-700">
                Tất cả {{ $counts['ok'] }} hạng mục đang trong trạng thái ổn định.
            </p>
        @endif
    @endif
</section>
