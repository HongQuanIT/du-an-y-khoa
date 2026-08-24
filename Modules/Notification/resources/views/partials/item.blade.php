@php
    /** @var \Modules\Notification\Models\UserNotification $notification */
    $compact = $compact ?? false;
    $unread = $notification->read_at === null;
@endphp

<div data-notification-id="{{ $notification->id }}"
    @class([
        'border-b border-outline-variant/60 last:border-0',
        'bg-primary-fixed/15' => $unread,
        'px-4 py-3' => $compact,
        'rounded-xl border border-outline-variant px-4 py-4' => ! $compact,
    ])>
    <div class="flex gap-3">
        <span class="material-symbols-outlined mt-0.5 shrink-0 text-[22px] text-primary">{{ $notification->icon() }}</span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <p class="font-label-md text-label-md font-semibold text-on-surface">{{ $notification->title }}</p>
                @unless ($compact)
                    <span class="rounded-md bg-surface-container px-2 py-0.5 font-label-sm text-label-sm text-on-surface-variant">
                        {{ $notification->categoryLabel() }}
                    </span>
                @endunless
            </div>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{{ $notification->body }}</p>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <span class="font-label-sm text-label-sm text-on-surface-variant">
                    {{ $notification->created_at?->diffForHumans() }}
                </span>
                <div class="flex items-center gap-3">
                    @if ($unread)
                        <form method="post" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <button type="submit" class="font-label-sm text-label-sm text-primary hover:underline">
                                {{ $notification->action_url ? 'Mở' : 'Đã đọc' }}
                            </button>
                        </form>
                    @elseif ($notification->action_url)
                        <a href="{{ $notification->action_url }}" class="font-label-sm text-label-sm text-primary hover:underline">Mở</a>
                    @endif
                    @unless ($compact)
                        <form method="post" action="{{ route('notifications.destroy', $notification) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="font-label-sm text-label-sm text-on-surface-variant hover:text-error">Xóa</button>
                        </form>
                    @endunless
                </div>
            </div>
        </div>
    </div>
</div>
