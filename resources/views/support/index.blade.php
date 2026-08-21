@php
    $labels = ['account' => 'Tài khoản', 'billing' => 'Thanh toán', 'course' => 'Khóa học', 'system' => 'Lỗi hệ thống', 'other' => 'Vấn đề khác'];
    $statusLabels = ['ai_active' => 'Trợ lý AI đang hỗ trợ', 'waiting_admin' => 'Đang chờ quản trị viên', 'admin_active' => 'Quản trị viên đang hỗ trợ', 'resolved' => 'Đã giải quyết'];
@endphp

<x-layouts.app title="Hỗ trợ">
    <div class="mx-auto grid max-w-6xl gap-5 lg:grid-cols-[280px_1fr]">
        <aside class="rounded-xl border border-outline-variant bg-surface p-4">
            <h2 class="font-title-md text-on-surface">Yêu cầu hỗ trợ</h2>
            <form method="post" action="{{ route('support.store') }}" class="mt-4 space-y-3">
                @csrf
                <select name="category" class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2" required>
                    @foreach ($labels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <input name="subject" maxlength="160" placeholder="Tiêu đề (không bắt buộc)" class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2">
                <textarea name="message" required maxlength="4000" rows="4" placeholder="Mô tả vấn đề của bạn…" class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2"></textarea>
                <button class="w-full rounded-lg bg-primary px-4 py-2.5 font-semibold text-on-primary">Bắt đầu chat</button>
            </form>
            <div class="mt-5 space-y-2 border-t border-outline-variant pt-4">
                @foreach ($conversations as $item)
                    <a href="{{ route('support.index', ['conversation' => $item->id]) }}" class="block rounded-lg p-3 {{ $conversation?->id === $item->id ? 'bg-primary-container' : 'hover:bg-surface-container-low' }}">
                        <p class="truncate font-semibold text-on-surface">{{ $item->subject ?: $labels[$item->category] }}</p>
                        <p class="mt-1 text-sm text-on-surface-variant">{{ $statusLabels[$item->status] }}</p>
                    </a>
                @endforeach
            </div>
        </aside>

        <section class="flex min-h-[560px] max-h-[calc(100dvh-var(--header-height)-3rem)] flex-col overflow-hidden rounded-xl border border-outline-variant bg-surface">
            @if ($conversation)
                <header class="shrink-0 border-b border-outline-variant px-5 py-4">
                    <p class="font-title-md text-on-surface">{{ $conversation->subject ?: $labels[$conversation->category] }}</p>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ $statusLabels[$conversation->status] }} · Không gửi mật khẩu, OTP hay thông tin thẻ.</p>
                </header>
                <div
                    id="support-messages"
                    data-support-user-thread
                    data-conversation-id="{{ $conversation->id }}"
                    data-awaiting-ai="{{ $conversation->status === 'ai_active' ? '1' : '0' }}"
                    data-message-url="{{ route('support.messages.store', $conversation) }}"
                    class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5"
                    role="log"
                    aria-live="polite"
                >
                    @foreach ($conversation->messages as $message)
                        <div class="flex {{ $message->sender_type === 'user' ? 'justify-end' : 'justify-start' }}" data-message-id="{{ $message->id }}">
                            <div class="max-w-[80%] rounded-2xl px-4 py-3 {{ $message->sender_type === 'user' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface' }}">
                                <p class="mb-1 text-xs opacity-70">{{ $message->sender_type === 'ai' ? 'Trợ lý AI' : ($message->sender_type === 'admin' ? 'Quản trị viên' : 'Bạn') }}</p>
                                <p class="whitespace-pre-wrap">{{ $message->body }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($conversation->status !== 'resolved')
                    <form id="support-message-form" method="post" action="{{ route('support.messages.store', $conversation) }}" class="flex shrink-0 gap-3 border-t border-outline-variant p-4" data-support-user-form>
                        @csrf
                        <textarea id="support-message-input" name="message" required maxlength="4000" rows="2" placeholder="Nhập tin nhắn…" class="min-h-0 flex-1 rounded-lg border border-outline-variant bg-surface px-3 py-2" data-support-user-input></textarea>
                        <button id="support-message-send" type="submit" class="rounded-lg bg-primary px-4 font-semibold text-on-primary disabled:cursor-not-allowed disabled:opacity-50">Gửi</button>
                    </form>
                @endif
            @else
                <div class="m-auto max-w-md p-8 text-center"><span class="material-symbols-outlined text-5xl text-primary">support_agent</span><h1 class="mt-4 font-headline-sm text-on-surface">Chúng tôi sẵn sàng hỗ trợ</h1><p class="mt-2 text-on-surface-variant">Chọn nhóm vấn đề và bắt đầu cuộc trò chuyện. Trợ lý AI sẽ hỗ trợ trước, sau đó chuyển quản trị viên khi cần.</p></div>
            @endif
        </section>
    </div>
</x-layouts.app>
