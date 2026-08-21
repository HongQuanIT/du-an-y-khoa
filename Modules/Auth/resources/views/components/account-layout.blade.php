@props([
    'accountActive' => 'career',
    'accountTitle' => 'Tài khoản',
    'accountDescription' => null,
])

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
