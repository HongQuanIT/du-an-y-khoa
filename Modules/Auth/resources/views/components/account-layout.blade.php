@props([
    'accountActive' => 'career',
    'accountTitle' => 'Tài khoản',
    'accountDescription' => null,
])

@php
    use App\Support\Auth\Staff;

    $useAdminShell = Staff::isStaff(auth()->user());
@endphp

@if ($useAdminShell)
    <x-layouts.admin :title="$accountTitle">
        <x-admin.page-header :title="$accountTitle" :description="$accountDescription" />

        <div class="space-y-6">
            @include('auth::partials.account-alerts')

            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10">
                <aside class="lg:w-56 lg:shrink-0">
                    @include('auth::partials.account-nav', ['active' => $accountActive])
                </aside>

                <main class="min-w-0 flex-1 space-y-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </x-layouts.admin>
@else
    <x-layouts.app :title="$accountTitle">
        <div class="mx-auto max-w-5xl px-margin-mobile py-8 md:px-gutter md:py-12">
            <div class="mb-6 flex flex-col gap-1 sm:mb-8">
                <h1 class="font-headline-md text-headline-md text-on-surface">{{ $accountTitle }}</h1>
                @if ($accountDescription)
                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $accountDescription }}</p>
                @endif
            </div>

            <div class="space-y-6">
                @include('auth::partials.account-alerts')

                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10">
                    <aside class="lg:w-56 lg:shrink-0">
                        @include('auth::partials.account-nav', ['active' => $accountActive])
                    </aside>

                    <div class="min-w-0 flex-1 space-y-6">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </x-layouts.app>
@endif
