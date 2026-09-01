@props([
    'actions' => [],
])

@if (count($actions) > 0)
    <section {{ $attributes->class(['rounded-xl border border-outline-variant bg-surface p-5']) }}>
        <div class="mb-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Thao tác nhanh</h3>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Lối tắt theo quyền của bạn</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($actions as $action)
                <a href="{{ $action['href'] }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-4 py-2.5 font-label-md text-label-md text-on-surface transition hover:border-primary/40 hover:bg-primary/5 hover:text-primary">
                    <span class="material-symbols-outlined text-[20px]">{{ $action['icon'] }}</span>
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </section>
@endif
