@props(['mode' => 'login', 'planPriceId' => null])

@php
    $socialProviders = collect([
        ['key' => 'google', 'label' => 'Google'],
        ['key' => 'facebook', 'label' => 'Facebook'],
    ])->filter(function (array $provider): bool {
        $settings = config('services.'.$provider['key'], []);

        return (bool) ($settings['enabled'] ?? false)
            && filled($settings['client_id'] ?? null)
            && filled($settings['client_secret'] ?? null);
    });
@endphp

@if ($socialProviders->isNotEmpty())
    <div class="relative my-8">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-border"></div></div>
        <div class="relative flex justify-center font-label-sm text-label-sm">
            <span class="bg-surface px-4 uppercase tracking-wider text-on-surface-variant">Hoặc tiếp tục với</span>
        </div>
    </div>

    <div>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($socialProviders as $provider)
                <form class="min-w-0" method="post" action="{{ route('social.redirect', ['provider' => $provider['key']]) }}">
                    @csrf
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    @if ($planPriceId)
                        <input type="hidden" name="plan_price_id" value="{{ $planPriceId }}">
                    @endif
                    <button type="submit"
                        class="flex h-11 w-full items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-border bg-surface px-2 font-label-md text-label-md font-normal text-on-surface transition-colors hover:bg-surface-container-low">
                        @if ($provider['key'] === 'google')
                            <svg viewBox="0 0 24 24" class="size-5 shrink-0" aria-hidden="true">
                                <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.55h3.24c1.9-1.75 2.98-4.33 2.98-7.42Z"/>
                                <path fill="#34A853" d="M12 22c2.7 0 4.98-.9 6.63-2.35l-3.24-2.55c-.9.6-2.05.96-3.39.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.63A10 10 0 0 0 12 22Z"/>
                                <path fill="#FBBC05" d="M6.39 13.93A6.02 6.02 0 0 1 6.08 12c0-.67.11-1.32.31-1.93V7.44H3.04A10 10 0 0 0 2 12c0 1.61.38 3.14 1.04 4.56l3.35-2.63Z"/>
                                <path fill="#EA4335" d="M12 5.94c1.47 0 2.79.5 3.82 1.49l2.88-2.88A9.65 9.65 0 0 0 12 2a10 10 0 0 0-8.96 5.44l3.35 2.63C7.18 7.7 9.39 5.94 12 5.94Z"/>
                            </svg>
                        @else
                            <svg viewBox="0 0 24 24" class="size-5 shrink-0" aria-hidden="true">
                                <path fill="#1877F2" d="M24 12a12 12 0 1 0-13.88 11.85v-8.47H7.08V12h3.04V9.42c0-3 1.79-4.66 4.53-4.66 1.31 0 2.69.23 2.69.23v2.96h-1.51c-1.5 0-1.96.93-1.96 1.88V12h3.33l-.53 3.38h-2.8v8.47A12 12 0 0 0 24 12Z"/>
                                <path fill="#fff" d="m16.67 15.38.53-3.38h-3.33V9.83c0-.95.46-1.88 1.96-1.88h1.51V4.99s-1.38-.23-2.69-.23c-2.74 0-4.53 1.66-4.53 4.66V12H7.08v3.38h3.04v8.47a12.2 12.2 0 0 0 3.75 0v-8.47h2.8Z"/>
                            </svg>
                        @endif
                        <span>Tiếp tục với {{ $provider['label'] }}</span>
                    </button>
                </form>
            @endforeach
        </div>
        <p class="mt-3 text-center text-label-sm leading-relaxed text-on-surface-variant">
            Khi tiếp tục, bạn đồng ý với <a href="{{ route('landing.terms') }}" class="font-semibold text-primary hover:underline">Điều khoản</a>
            và <a href="{{ route('landing.privacy') }}" class="font-semibold text-primary hover:underline">Chính sách bảo mật</a>.
        </p>
    </div>
@endif
