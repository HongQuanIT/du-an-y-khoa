@props([
    'items' => [],
    'viewAllHref' => null,
])

<section {{ $attributes->class(['rounded-xl border border-outline-variant bg-surface p-5']) }}>
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Hoạt động quản trị gần đây</h3>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">10 hành động mới nhất từ nhật ký audit</p>
        </div>
        @if ($viewAllHref)
            <a href="{{ $viewAllHref }}" class="font-label-sm text-label-sm text-primary hover:underline">Xem nhật ký</a>
        @endif
    </div>

    @if (count($items) === 0)
        <div class="rounded-lg border border-dashed border-outline-variant px-4 py-8 text-center">
            <span class="material-symbols-outlined mb-2 text-[32px] text-on-surface-variant">history</span>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Chưa có hoạt động quản trị nào được ghi nhận.</p>
        </div>
    @else
        <ul class="divide-y divide-outline-variant/60">
            @foreach ($items as $item)
                <li class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-surface-container-high">
                        <span class="material-symbols-outlined text-[18px] text-on-surface-variant">bolt</span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-body-sm text-body-sm text-on-surface">
                            @if ($item['actor_name'])
                                <span class="font-label-md">{{ $item['actor_name'] }}</span>
                            @else
                                <span class="text-on-surface-variant">Hệ thống</span>
                            @endif
                            <span class="text-on-surface-variant"> · </span>
                            <a href="{{ $item['href'] }}" class="text-primary hover:underline">{{ $item['action_label'] }}</a>
                        </p>
                        @if ($item['subject_label'])
                            <p class="mt-0.5 font-label-sm text-label-sm text-on-surface-variant">
                                @if ($item['subject_href'])
                                    <a href="{{ $item['subject_href'] }}" class="hover:text-primary hover:underline">{{ $item['subject_label'] }}</a>
                                @else
                                    {{ $item['subject_label'] }}
                                @endif
                            </p>
                        @endif
                        <p class="mt-1 font-label-sm text-label-sm text-on-surface-variant/80">
                            {{ $item['occurred_at']->diffForHumans() }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
