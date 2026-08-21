<x-layouts.admin title="Hỗ trợ · {{ $conversation->user->name }}">
    @php
        $requiresTakeoverConfirm = $requiresTakeoverConfirm ?? false;
        $handlerName = $conversation->assignedAdmin?->name ?? 'một quản trị viên khác';
        $messages = $conversation->messages;
        $prevMessage = null;
    @endphp

    <script>document.body.dataset.supportConversationId = @json((string) $conversation->id);</script>

    @if ($requiresTakeoverConfirm)
        <div data-support-takeover-modal class="fixed inset-0 z-[70] flex items-center justify-center bg-black/45 p-4">
            <div class="w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="support-takeover-title">
                <h2 id="support-takeover-title" class="font-title-md font-bold text-on-surface">Phiên chat đang được xử lý</h2>
                <p class="mt-2 text-sm text-on-surface-variant">
                    <span class="font-semibold text-on-surface">{{ $handlerName }}</span> đang xử lý hội thoại này.
                    Bạn có muốn tiếp nhận và xử lý không?
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <a href="{{ route('admin.support.index') }}" class="rounded-lg border border-outline-variant px-4 py-2 text-sm font-semibold text-on-surface-variant">Quay lại</a>
                    <form method="post" action="{{ route('admin.support.claim', $conversation) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary">Tiếp nhận xử lý</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Viewport-locked: header + composer stay visible; only messages scroll. --}}
    <div class="flex h-[calc(100dvh-var(--spacing-header-height)-2*var(--spacing-margin-mobile))] flex-col gap-3 overflow-hidden md:h-[calc(100dvh-var(--spacing-header-height)-2*var(--spacing-margin-desktop))] {{ $requiresTakeoverConfirm ? 'pointer-events-none select-none opacity-60' : '' }}">
        <header class="flex shrink-0 items-start justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('admin.support.index') }}" class="text-sm text-primary">← Danh sách hỗ trợ</a>
                <h1 class="mt-1 truncate font-headline-sm text-on-surface">{{ $conversation->subject ?: 'Yêu cầu hỗ trợ' }}</h1>
                <p class="truncate text-on-surface-variant">{{ $conversation->user->name }} · {{ $conversation->user->email }}</p>
                <p class="mt-1 text-xs text-on-surface-variant">
                    Người xử lý:
                    <span class="font-semibold text-on-surface">{{ $conversation->assignedAdmin?->name ?? 'Chưa có' }}</span>
                    · {{ $conversation->adminWorkflowStatus()['label'] }}
                </p>
            </div>
            @if ($conversation->status !== 'resolved' && ! $requiresTakeoverConfirm)
                <form method="post" action="{{ route('admin.support.resolve', $conversation) }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="rounded-lg border border-outline-variant bg-surface px-4 py-2 text-sm whitespace-nowrap">Đánh dấu đã xử lý</button>
                </form>
            @elseif ($conversation->status === 'resolved')
                <span class="shrink-0 rounded-full bg-surface-container px-3 py-1.5 text-xs font-semibold text-on-surface-variant">Đã đóng</span>
            @endif
        </header>

        <section
            @unless ($requiresTakeoverConfirm)
                data-support-admin-thread
                data-conversation-id="{{ $conversation->id }}"
                data-user-label="{{ $conversation->user->name }}"
                data-seen-url="{{ route('admin.support.seen', $conversation) }}"
            @endunless
            class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-outline-variant bg-surface"
        >
            <div data-support-messages id="support-messages" class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain p-5" role="log" aria-live="polite">
                @foreach ($messages as $message)
                    @php
                        $showTime = $prevMessage === null
                            || $prevMessage->sender_type !== $message->sender_type
                            || (int) ($prevMessage->sender_id ?? 0) !== (int) ($message->sender_id ?? 0)
                            || abs($message->created_at->diffInSeconds($prevMessage->created_at)) >= 120;
                        $senderLabel = $message->sender_type === 'ai'
                            ? 'Trợ lý AI'
                            : ($message->sender_type === 'admin'
                                ? ((int) $message->sender_id === (int) $admin->id ? 'Bạn' : ($message->sender?->name ?? 'Admin'))
                                : $conversation->user->name);
                        $timeLabel = $message->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s');
                    @endphp
                    <div
                        class="flex {{ $message->sender_type === 'admin' ? 'justify-end' : 'justify-start' }}"
                        data-message-id="{{ $message->id }}"
                        data-sender-type="{{ $message->sender_type }}"
                        data-sender-id="{{ $message->sender_id ?? '' }}"
                        data-created-at="{{ $message->created_at?->toIso8601String() }}"
                    >
                        <div class="flex max-w-[80%] flex-col {{ $message->sender_type === 'admin' ? 'items-end' : 'items-start' }} gap-1">
                            @if ($showTime && $timeLabel)
                                <time datetime="{{ $message->created_at?->toIso8601String() }}" class="px-1 text-[11px] text-on-surface-variant">{{ $timeLabel }}</time>
                            @endif
                            <div class="rounded-2xl px-4 py-3 {{ $message->sender_type === 'admin' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface' }}">
                                <p class="mb-1 text-xs opacity-70">{{ $senderLabel }}</p>
                                <p class="whitespace-pre-wrap">{{ $message->body }}</p>
                            </div>
                        </div>
                    </div>
                    @php $prevMessage = $message; @endphp
                @endforeach
            </div>

            @if ($conversation->status !== 'resolved' && ! $requiresTakeoverConfirm)
                <form data-support-message-form id="admin-support-message-form" method="post" action="{{ route('admin.support.messages.store', $conversation) }}" class="flex shrink-0 items-end gap-3 border-t border-outline-variant bg-surface p-4">
                    @csrf
                    <textarea data-support-message-input id="admin-support-message-input" name="message" required maxlength="4000" rows="2" class="min-h-[2.75rem] max-h-32 min-w-0 flex-1 resize-y rounded-lg border border-outline-variant bg-surface px-3 py-2" placeholder="Phản hồi người dùng…"></textarea>
                    <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 font-semibold text-on-primary">Gửi</button>
                </form>
            @endif
        </section>
    </div>
</x-layouts.admin>
