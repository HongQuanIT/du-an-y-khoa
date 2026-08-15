@php
    use Modules\Admin\Support\Cms\ResolvedMenu;

    $footer = ResolvedMenu::footer();
@endphp

<footer class="bg-surface border-t border-border py-16 md:py-20">
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
        <div class="grid grid-cols-2 md:grid-cols-12 gap-8 md:gap-12 mb-12 md:mb-16">
            <div class="col-span-2 md:col-span-4">
                <a href="{{ route('landing.home') }}"
                    class="text-headline-md font-bold text-primary mb-6 block tracking-tight">{{ config('app.name') }}</a>
                @if ($footer['brand_blurb'] !== '')
                    <p class="text-body-md text-text-secondary mb-8 leading-relaxed max-w-sm">
                        {{ $footer['brand_blurb'] }}
                    </p>
                @endif
                <div class="flex gap-4">
                    <a href="#"
                        class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors border border-border">
                        <span class="material-symbols-outlined text-[20px]">share</span>
                    </a>
                    <a href="#"
                        class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors border border-border">
                        <span class="material-symbols-outlined text-[20px]">public</span>
                    </a>
                </div>
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
                {{ config('app.name') }}. Mọi quyền được bảo lưu. Phát triển bởi đội ngũ bác sĩ &amp; kỹ sư công
                nghệ.</p>
            <div class="flex items-center gap-8 text-label-sm text-text-secondary">
                @foreach ($footer['bottom_links'] as $link)
                    <a href="{{ $link['href'] }}" class="hover:text-primary transition-colors">{{ $link['label'] }}</a>
                @endforeach
            </div>
        </div>
    </div>
</footer>
