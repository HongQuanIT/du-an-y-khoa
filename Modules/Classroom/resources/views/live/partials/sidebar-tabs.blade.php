@php $mobile = $mobile ?? false; @endphp

<div class="flex h-full min-h-0 flex-col {{ $mobile ? '' : 'flex-1' }}"
    @unless($mobile) x-data="{ tab: 'chat' }" @endunless
>
    @unless($mobile)
        <div class="flex shrink-0 border-b border-outline-variant">
            <button type="button" @click="tab = 'chat'"
                :class="tab === 'chat' ? 'border-b-2 border-primary text-primary' : 'text-on-surface-variant'"
                class="flex-1 py-3 text-sm font-medium">Chat</button>
            @if ($canModerate && $session->hasQuestionSet())
                <button type="button" data-live-tab="questions" @click="tab = 'questions'"
                    :class="tab === 'questions' ? 'border-b-2 border-primary text-primary' : 'text-on-surface-variant'"
                    class="flex-1 py-3 text-sm font-medium">Đề</button>
            @endif
            <button type="button" @click="tab = 'participants'"
                :class="tab === 'participants' ? 'border-b-2 border-primary text-primary' : 'text-on-surface-variant'"
                class="flex-1 py-3 text-sm font-medium">
                Thành viên
            </button>
        </div>
    @endunless

    <div data-live-chat-panel
        class="flex min-h-0 flex-1 flex-col"
        @unless($mobile) x-show="tab === 'chat'" @endunless
        @if($mobile) x-show="mobileTab === 'chat'" @endif
    >
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-outline-variant px-4 py-2">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-on-surface">Chat</h2>
                <p data-live-chat-status
                    class="text-xs {{ $chatReadonly ? 'text-on-surface-variant' : ($session->chat_muted ? 'text-error' : 'hidden text-on-surface-variant') }}">
                    @if ($chatReadonly)
                        Chỉ đọc
                    @elseif ($session->chat_muted)
                        Chat đang tắt — học viên không gửi được tin
                    @endif
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <div data-live-chat-filter class="inline-flex rounded-lg bg-surface-container-low p-0.5 text-xs">
                    <button type="button" data-filter="all"
                        class="rounded-md bg-surface px-2 py-1 font-medium text-on-surface shadow-sm">
                        Tất cả
                    </button>
                    <button type="button" data-filter="question"
                        class="rounded-md px-2 py-1 font-medium text-on-surface-variant">
                        Chỉ hỏi
                    </button>
                </div>
                @if ($canModerate && $session->status->value === 'live')
                    <button type="button" data-live-mute-chat data-chat-muted="{{ $session->chat_muted ? '1' : '0' }}"
                        class="rounded-lg border border-outline-variant px-2 py-1 text-xs font-medium text-on-surface hover:bg-surface-container-low">
                        {{ $session->chat_muted ? 'Bật chat' : 'Tắt chat' }}
                    </button>
                @endif
            </div>
        </div>

        <div data-live-messages class="flex-1 space-y-2 overflow-y-auto p-3" aria-live="polite"></div>

        <div data-live-hands
            class="hidden shrink-0 border-t border-amber-200/80 bg-amber-50 px-3 py-2 text-on-surface">
            <div class="mb-1 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-amber-900">
                    <span class="material-symbols-outlined align-middle text-[16px]">back_hand</span>
                    Giơ tay (<span data-live-hands-count>0</span>)
                </p>
            </div>
            <ul data-live-hands-list class="space-y-1"></ul>
        </div>

        @if (! $chatReadonly)
            <div data-live-chat-compose
                class="{{ (! $canModerate && $session->chat_muted) ? 'hidden' : '' }} shrink-0">
                <form data-live-chat-form class="border-t border-outline-variant p-3">
                    <div class="flex gap-2">
                        <select data-live-msg-type name="type"
                            class="rounded-lg border-none bg-surface-container-low px-2 py-2 text-xs text-on-surface focus:ring-2 focus:ring-primary">
                            <option value="chat">Chat</option>
                            <option value="question">Hỏi</option>
                        </select>
                        <input data-live-msg-input type="text" name="body" required maxlength="2000"
                            placeholder="Nhắn tin..."
                            class="min-w-0 flex-1 rounded-lg border-none bg-surface-container-low px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-variant focus:ring-2 focus:ring-primary"
                            autocomplete="off">
                        <button type="submit" class="rounded-lg bg-primary px-3 py-2 text-white hover:opacity-90" aria-label="Gửi">
                            <span class="material-symbols-outlined text-[20px]">send</span>
                        </button>
                    </div>
                    <p data-live-chat-error class="mt-1 hidden text-xs text-error"></p>
                </form>
            </div>
            <div class="flex shrink-0 gap-2 border-t border-outline-variant px-3 py-2">
                <button type="button" data-live-raise-hand data-raised="0"
                    class="flex-1 rounded-lg border border-outline-variant bg-surface py-1.5 text-xs font-medium text-on-surface hover:bg-surface-container-low">
                    Giơ tay
                </button>
            </div>
            <p data-live-chat-muted-hint
                class="{{ ($canModerate || ! $session->chat_muted) ? 'hidden' : '' }} border-t border-outline-variant px-4 py-3 text-center text-xs text-on-surface-variant">
                Host đã tắt chat. Bạn vẫn xem được lịch sử tin nhắn.
            </p>
        @endif
    </div>

    @if ($canModerate && $session->hasQuestionSet())
        <div data-live-question-panel
            class="flex min-h-0 flex-1 flex-col overflow-hidden"
            @unless($mobile) x-show="tab === 'questions'" @endunless
            @if($mobile) x-show="mobileTab === 'questions'" @endif
        >
            @include('classroom::live.partials.question-panel')
        </div>
    @endif

    <div data-lk-participants-panel
        class="flex min-h-0 flex-1 flex-col"
        @unless($mobile) x-show="tab === 'participants'" @endunless
        @if($mobile) x-show="mobileTab === 'participants'" @endif
    >
        <div class="flex shrink-0 items-center justify-between border-b border-outline-variant px-4 py-3">
            <h2 class="font-semibold text-on-surface">Thành viên trong phòng</h2>
            <span data-lk-participant-count class="text-xs text-on-surface-variant">0</span>
        </div>
        <div data-lk-participants-empty class="px-4 py-8 text-center text-sm text-on-surface-variant">
            Chưa có ai trong phòng.
        </div>
        <ul data-lk-participants class="min-h-0 flex-1 divide-y divide-outline-variant overflow-y-auto"></ul>
    </div>
</div>
