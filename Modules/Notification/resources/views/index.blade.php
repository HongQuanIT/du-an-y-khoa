<x-dynamic-component :component="$layout" :title="$title">
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Trung tâm thông báo</h2>
                <p class="mt-1 font-body-md text-body-md text-on-surface-variant">
                    Cá nhân hoá theo hoạt động của bạn, kèm thông báo hệ thống.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if (request()->routeIs('admin.*') && auth()->user()?->can(\App\Support\Enums\Permission::NotificationBroadcast->value))
                    <a href="{{ route('admin.notifications.broadcast') }}"
                        class="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                        Gửi hệ thống
                    </a>
                @endif
                <form method="post" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit"
                        class="rounded-lg bg-primary px-4 py-2 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                        Đánh dấu tất cả đã đọc
                    </button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <p class="rounded-lg border border-primary/30 bg-primary-container/40 px-4 py-3 font-body-md text-body-md text-on-surface">
                {{ session('status') }}
            </p>
        @endif

        <div class="flex flex-wrap gap-2">
            @foreach ([
                'all' => 'Tất cả',
                'unread' => 'Chưa đọc',
            ] as $key => $label)
                <a href="{{ route($indexRoute, array_filter(['filter' => $key === 'all' ? null : $key, 'category' => $category ?: null])) }}"
                    @class([
                        'rounded-lg px-3 py-1.5 font-label-md text-label-md',
                        'bg-primary text-on-primary' => $filter === $key,
                        'bg-surface-container text-on-surface-variant hover:text-on-surface' => $filter !== $key,
                    ])>{{ $label }}</a>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route($indexRoute, array_filter(['filter' => $filter === 'all' ? null : $filter])) }}"
                @class([
                    'rounded-lg px-3 py-1.5 font-label-sm text-label-sm',
                    'bg-primary-container text-on-primary-container' => $category === '',
                    'border border-outline-variant text-on-surface-variant' => $category !== '',
                ])>Mọi loại</a>
            @foreach (\Modules\Notification\Support\NotificationCatalog::filterCategories() as $cat)
                <a href="{{ route($indexRoute, array_filter(['filter' => $filter === 'all' ? null : $filter, 'category' => $cat])) }}"
                    @class([
                        'rounded-lg px-3 py-1.5 font-label-sm text-label-sm',
                        'bg-primary-container text-on-primary-container' => $category === $cat,
                        'border border-outline-variant text-on-surface-variant' => $category !== $cat,
                    ])>{{ \Modules\Notification\Support\NotificationCatalog::categoryLabel($cat) }}</a>
            @endforeach
        </div>

        <div class="space-y-3">
            @forelse ($notifications as $notification)
                @include('notification::partials.item', ['notification' => $notification, 'compact' => false])
            @empty
                <p class="rounded-xl border border-dashed border-outline-variant px-6 py-12 text-center font-body-md text-body-md text-on-surface-variant">
                    Chưa có thông báo phù hợp bộ lọc.
                </p>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>

    <div data-notification-toasts class="pointer-events-none fixed right-4 bottom-4 z-[70] flex w-[min(100vw-2rem,360px)] flex-col gap-2"></div>
</x-dynamic-component>
