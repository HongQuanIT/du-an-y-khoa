@props([
    'indexRoute' => 'notifications.index',
    'closeAccountMenu' => true,
])

@php
    $unread = (int) ($headerUnreadCount ?? 0);
    $items = $headerNotifications ?? collect();
    $userId = auth()->id();
    $importantItem = $importantFlyoutNotification ?? null;
@endphp

<div class="relative" data-notification-bell data-user-id="{{ $userId }}"
    data-important="{{ json_encode($importantItem) }}"
    x-data="{
        flyoutOpen: false,
        importantItem: null,
        init() {
            try {
                this.importantItem = JSON.parse(this.$el.dataset.important || 'null');
            } catch (e) {}
            if (this.importantItem && this.importantItem.id) {
                const dismissed = sessionStorage.getItem('medlearn_notif_flyout_dismissed');
                if (!dismissed || String(this.importantItem.id) !== dismissed) {
                    setTimeout(() => {
                        this.flyoutOpen = true;
                    }, 500);
                }
            }
        },
        async dismissFlyout(markRead = true) {
            this.flyoutOpen = false;
            if (this.importantItem && this.importantItem.id) {
                sessionStorage.setItem('medlearn_notif_flyout_dismissed', String(this.importantItem.id));

                if (markRead) {
                    try {
                        const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
                        await fetch('/notifications/' + this.importantItem.id + '/read', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            }
                        });
                    } catch (e) {}
                }
            }
        },
        async markReadAndGo(item) {
            await this.dismissFlyout(false);
            if (!item) return;
            try {
                const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
                await fetch('/notifications/' + item.id + '/read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    }
                });
            } catch (e) {}

            if (item.action_url) {
                window.location.href = item.action_url;
            }
        }
    }"
    @click.outside="notificationsOpen = false; flyoutOpen = false">

    <button type="button"
        @click="notificationsOpen = !notificationsOpen; flyoutOpen = false"
        class="group relative inline-flex size-10 cursor-pointer items-center justify-center rounded-full transition-colors hover:bg-surface-container"
        :aria-expanded="notificationsOpen" aria-label="Thông báo">
        <span
            class="material-symbols-outlined text-[24px] leading-none text-on-surface-variant group-hover:text-primary">notifications</span>
        <span data-notification-badge
            class="absolute top-2 right-2 size-2 rounded-full border-2 border-surface bg-error {{ $unread > 0 ? '' : 'hidden' }}"></span>
    </button>

    {{-- Cửa sổ nổi thông báo quan trọng gần biểu tượng chuông (không che toàn màn hình) --}}
    <div x-show="flyoutOpen && !notificationsOpen" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="absolute right-0 top-full z-50 mt-2 w-[min(100vw-2rem,360px)] sm:w-[380px] overflow-hidden rounded-2xl border border-outline-variant bg-surface p-4 shadow-xl ring-1 ring-black/5"
        role="alert"
        aria-live="polite">

        <div class="flex items-start justify-between gap-2 pb-2">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                    :class="importantItem?.badgeClass || 'bg-primary/10 text-primary'">
                    <span class="material-symbols-outlined text-[14px]" x-text="importantItem?.icon || 'notifications'"></span>
                    <span x-text="importantItem?.badgeLabel || 'Thông báo quan trọng'"></span>
                </span>
            </div>
            <button type="button" @click="dismissFlyout()"
                class="rounded-lg p-1 text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors"
                aria-label="Đóng thông báo">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <div class="mt-1">
            <h4 class="font-label-md text-label-md font-bold text-on-surface leading-snug" x-text="importantItem?.title"></h4>
            <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant line-clamp-2 leading-relaxed" x-text="importantItem?.body"></p>
        </div>

        <div class="mt-3 flex items-center justify-between gap-2 border-t border-outline-variant/60 pt-2.5">
            <span class="text-[11px] text-on-surface-variant" x-text="importantItem?.created_at_human"></span>
            <div class="flex items-center gap-2">
                <button type="button" @click="dismissFlyout()"
                    class="rounded-lg px-2.5 py-1 text-xs font-medium text-on-surface-variant hover:bg-surface-container transition-colors">
                    Bỏ qua
                </button>
                <button type="button" @click="markReadAndGo(importantItem)"
                    class="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-1 text-xs font-bold text-white shadow-sm hover:bg-primary-container hover:text-on-primary-container active:scale-95 transition-all">
                    <span x-text="importantItem?.action_url ? (importantItem?.cta || 'Xem ngay') : 'Đã hiểu'"></span>
                    <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Dropdown danh sách thông báo --}}
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
