@props([
    'accountActive' => 'career',
    'accountTitle' => 'Tài khoản',
    'accountDescription' => null,
])

<x-layouts.app :title="$accountTitle">
    <div class="mx-auto w-full max-w-[1040px] px-margin-mobile py-8 md:px-margin-desktop md:py-10">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10">
            <aside class="lg:w-56 lg:shrink-0">
                @include('auth::partials.account-nav', ['active' => $accountActive])
            </aside>

            <main class="min-w-0 flex-1 space-y-6">
                <header class="space-y-1">
                    <h1 class="font-headline-lg text-headline-lg-mobile text-on-surface md:text-headline-lg">
                        {{ $accountTitle }}
                    </h1>
                    @if ($accountDescription)
                        <p class="font-body-md text-body-md text-on-surface-variant">{{ $accountDescription }}</p>
                    @endif
                </header>

                @include('auth::partials.account-alerts')

                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.app>
