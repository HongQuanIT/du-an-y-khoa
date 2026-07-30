@props(['tagline'])

{{-- Two-column auth screen: branding on the left, form slot on the right. --}}
<div class="flex min-h-screen">
    <x-auth.branding :tagline="$tagline" />

    <div class="w-full lg:w-1/2 bg-surface flex flex-col justify-center p-6 sm:p-12 lg:p-16">
        <div class="max-w-md mx-auto w-full">
            <div class="lg:hidden mb-8 text-center">
                <a href="{{ route('landing.home') }}"
                    class="font-headline-lg text-headline-lg font-extrabold text-primary tracking-tight">{{ config('app.name') }}</a>
            </div>

            {{ $slot }}
        </div>
    </div>
</div>
