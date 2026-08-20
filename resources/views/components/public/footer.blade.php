@php
    use Modules\Admin\Support\Cms\ResolvedMenu;

    $footer = ResolvedMenu::footer();
    $siteName = setting('general.site_name', config('app.name'));
    $fanpageUrl = setting('general.fanpage_url');
    $zaloUrl = setting('general.zalo_url');
    $supportEmail = setting('general.support_email');
    $supportHotline = setting('general.support_hotline');
@endphp

<footer class="bg-surface border-t border-border py-16 md:py-20">
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
        <div class="grid grid-cols-2 md:grid-cols-12 gap-8 md:gap-12 mb-12 md:mb-16">
            <div class="col-span-2 md:col-span-4">
                <a href="{{ route('landing.home') }}"
                    class="text-headline-md font-bold text-primary mb-6 block tracking-tight">{{ $siteName }}</a>
                @if ($footer['brand_blurb'] !== '')
                    <p class="text-body-md text-text-secondary mb-8 leading-relaxed max-w-sm">
                        {{ $footer['brand_blurb'] }}
                    </p>
                @endif
                @if ($fanpageUrl || $zaloUrl || $supportEmail || $supportHotline)
                    <div class="flex flex-wrap gap-4">
                        @if ($fanpageUrl)
                            <a href="{{ $fanpageUrl }}"
                                class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors border border-border"
                                aria-label="Fanpage">
                                <span class="material-symbols-outlined text-[20px]">public</span>
                            </a>
                        @endif
                        @if ($zaloUrl)
                            <a href="{{ $zaloUrl }}"
                                class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors border border-border"
                                aria-label="Zalo">
                                <span class="material-symbols-outlined text-[20px]">chat</span>
                            </a>
                        @endif
                        @if ($supportEmail)
                            <a href="mailto:{{ $supportEmail }}"
                                class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors border border-border"
                                aria-label="Email hỗ trợ">
                                <span class="material-symbols-outlined text-[20px]">mail</span>
                            </a>
                        @endif
                        @if ($supportHotline)
                            <a href="tel:{{ preg_replace('/\s+/', '', $supportHotline) }}"
                                class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors border border-border"
                                aria-label="Hotline hỗ trợ">
                                <span class="material-symbols-outlined text-[20px]">call</span>
                            </a>
                        @endif
                    </div>
                    @if ($supportEmail || $supportHotline)
                        <div class="mt-4 space-y-1 text-body-sm text-text-secondary">
                            @if ($supportEmail)
                                <p>{{ $supportEmail }}</p>
                            @endif
                            @if ($supportHotline)
                                <p>{{ $supportHotline }}</p>
                            @endif
                        </div>
                    @endif
                @endif
            </div>

            @foreach ($footer['columns'] as $column)
                <div class="md:col-span-2">
                    <h4 class="font-bold mb-6 text-on-surface">{{ $column['title'] }}</h4>
                    <ul class="space-y-4 text-body-sm text-text-secondary">
                        @foreach ($column['links'] as $link)
                            <li>
                                <a class="hover:text-primary transition-colors"
                                    href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="pt-10 border-t border-border flex flex-col md:flex-row justify-between items-center gap-6">
            <p class="text-label-sm text-text-secondary text-center md:text-left">© {{ date('Y') }}
                {{ $siteName }}. Mọi quyền được bảo lưu. Phát triển bởi đội ngũ bác sĩ &amp; kỹ sư công
                nghệ.</p>
            <div class="flex items-center gap-8 text-label-sm text-text-secondary">
                @foreach ($footer['bottom_links'] as $link)
                    <a href="{{ $link['href'] }}" class="hover:text-primary transition-colors">{{ $link['label'] }}</a>
                @endforeach
            </div>
        </div>
    </div>
</footer>
