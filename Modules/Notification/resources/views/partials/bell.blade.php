@props([
    'indexRoute' => 'notifications.index',
    'closeAccountMenu' => true,
])

@php
    $unread = (int) ($headerUnreadCount ?? 0);
    $items = $headerNotifications ?? collect();
    $userId = auth()->id();
@endphp

<div class="relative" data-notification-bell data-user-id="{{ $userId }}"
    @click.outside="notificationsOpen = false">
    <button type="button"
        @click="notificationsOpen = !notificationsOpen; {{ $closeAccountMenu ? 'accountMenu = false;' : '' }}"
        class="group relative inline-flex size-10 cursor-pointer items-center justify-center rounded-full transition-colors hover:bg-surface-container"
        :aria-expanded="notificationsOpen" aria-label="Thông báo">
        <span
            class="material-symbols-outlined text-[24px] leading-none text-on-surface-variant group-hover:text-primary">notifications</span>
        <span data-notification-badge
            class="absolute top-2 right-2 size-2 rounded-full border-2 border-surface bg-error {{ $unread > 0 ? '' : 'hidden' }}"></span>
    </button>

    <section x-show="notificationsOpen" x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="translate-y-1 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        class="absolute right-0 z-50 mt-2 w-[min(100vw-2rem,380px)] overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-lg">
        <div class="flex items-center justify-between border-b border-outline-variant px-4 py-3">
            <p class="font-label-md text-label-md font-semibold text-on-surface">Thông báo</p>
            <div class="flex items-center gap-3">
                <form method="post" action="{{ route('notifications.read-all') }}" data-notification-read-all
                    class="{{ $unread > 0 ? '' : 'hidden' }}">
                    @csrf
                    <button type="submit" class="font-label-sm text-label-sm text-primary hover:underline">
                        Đánh dấu đã đọc
                    </button>
                </form>
                <a href="{{ route($indexRoute) }}" class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary">
                    Xem tất cả
                </a>
            </div>
        </div>
        <div class="max-h-80 overflow-y-auto" data-notification-list>
            @forelse ($items as $notification)
                @include('notification::partials.item', [
                    'notification' => $notification,
                    'compact' => true,
                ])
            @empty
                <p data-notification-empty class="px-4 py-6 font-body-md text-body-md text-on-surface-variant">
                    Chưa có thông báo.
                </p>
            @endforelse
        </div>
    </section>
</div>
