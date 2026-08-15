@props([
    'placement' => 'landing',
])

@php
    use Modules\Admin\Support\Cms\ActiveBanners;
    use Modules\Admin\Support\Enums\BannerPlacement;
    use Modules\Admin\Support\Enums\BannerVariant;

    $placementEnum = BannerPlacement::tryFrom($placement) ?? BannerPlacement::Landing;
    $banners = ActiveBanners::for($placementEnum, auth()->user());

    $variantClasses = [
        BannerVariant::Info->value => 'border-primary/25 bg-primary/10 text-on-surface',
        BannerVariant::Promo->value => 'border-transparent text-white premium-gradient shadow-md',
        BannerVariant::Warning->value => 'border-amber-500/30 bg-amber-50 text-amber-950',
        BannerVariant::Success->value => 'border-emerald-500/30 bg-emerald-50 text-emerald-950',
    ];

    $ctaClasses = [
        BannerVariant::Info->value => 'bg-primary text-on-primary hover:opacity-90',
        BannerVariant::Promo->value => 'bg-white text-[#FF5E62] hover:opacity-90',
        BannerVariant::Warning->value => 'bg-amber-600 text-white hover:opacity-90',
        BannerVariant::Success->value => 'bg-emerald-700 text-white hover:opacity-90',
    ];
@endphp

@if ($banners->isNotEmpty())
    <div {{ $attributes->class(['space-y-3']) }}>
        @foreach ($banners as $banner)
            <div
                x-data="{
                    id: {{ $banner->id }},
                    show: true,
                    init() {
                        @if ($banner->is_dismissible)
                            try {
                                if (localStorage.getItem('cms_banner_dismissed_' + this.id) === '1') {
                                    this.show = false;
                                }
                            } catch (e) {}
                        @endif
                    },
                    dismiss() {
                        this.show = false;
                        @if ($banner->is_dismissible)
                            try { localStorage.setItem('cms_banner_dismissed_' + this.id, '1'); } catch (e) {}
                        @endif
                    }
                }"
                x-show="show"
                x-cloak
                @class([
                    'relative flex flex-col gap-3 rounded-xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-5',
                    $variantClasses[$banner->variant->value] ?? $variantClasses['info'],
                ])
                role="status">
                <div class="min-w-0 flex-1 pr-8 sm:pr-0">
                    <p class="font-body-md text-body-md leading-relaxed {{ $banner->variant === BannerVariant::Promo ? 'opacity-95' : '' }}">
                        {{ $banner->body }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @if ($banner->cta_label && $banner->cta_url)
                        <a href="{{ str_starts_with($banner->cta_url, 'http') ? $banner->cta_url : url($banner->cta_url) }}"
                            @class([
                                'inline-flex items-center justify-center rounded-lg px-4 py-2 font-label-md text-label-md transition',
                                $ctaClasses[$banner->variant->value] ?? $ctaClasses['info'],
                            ])>
                            {{ $banner->cta_label }}
                        </a>
                    @endif
                    @if ($banner->is_dismissible)
                        <button type="button" @click="dismiss()"
                            class="absolute top-2.5 right-2.5 inline-flex size-8 items-center justify-center rounded-lg opacity-70 transition hover:bg-black/5 hover:opacity-100 sm:static"
                            aria-label="Đóng thông báo">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
