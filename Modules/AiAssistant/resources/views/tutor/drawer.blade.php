@php
    /**
     * AI Tutor 1-tap drawer — reusable across Study / Review.
     *
     * @var array{type:string,id:string,session_id:?string,source:string,answered:bool,is_correct:?bool,label:string} $aiContext
     */
    $aiContext = $aiContext ?? [];
    $aiConfig = [
        'context' => [
            'type' => $aiContext['type'] ?? 'question',
            'id' => (string) ($aiContext['id'] ?? ''),
            'session_id' => $aiContext['session_id'] ?? null,
            'source' => $aiContext['source'] ?? 'session',
        ],
        'label' => $aiContext['label'] ?? 'Câu hỏi',
        'userId' => (int) (auth()->id() ?? 0),
        'routes' => [
            'threads' => url('/ai/threads'),
        ],
        'csrf' => csrf_token(),
    ];
@endphp

<div
    x-data="{
        cfg: @js($aiConfig),
        open: false,
        threadId: null,
        autoStarted: false,
        streaming: false,
        input: '',
        error: '',
        messages: [],
        quota: null,
        currentAssistantId: null,
        subscribed: false,

        storageKey() {
            return 'aiTutorThread:' + this.cfg.context.type + ':' + this.cfg.context.id;
        },
        headers(extra = {}) {
            return Object.assign({
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            }, extra);
        },
        scrollDown() {
            this.$nextTick(() => {
                const list = this.$refs.list;
                if (list) list.scrollTop = list.scrollHeight;
            });
        },
        async toggle() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.$refs.input?.focus());
                if (!this.autoStarted) {
                    await this.autoStart();
                }
            }
        },
        close() { this.open = false; },

        async autoStart() {
            this.autoStarted = true;
            this.error = '';
            const idem = 'thread:' + this.cfg.context.id + ':' + (this.cfg.context.source || 'session');
            try {
                const res = await fetch(this.cfg.routes.threads, {
                    method: 'POST',
                    headers: this.headers({ 'Idempotency-Key': idem }),
                    body: JSON.stringify({
                        context: this.cfg.context,
                        auto_start: true,
                    }),
                });
                const body = await res.json();
                if (!res.ok) {
                    this.handleGateError(res.status, body);
                    return;
                }
                const data = body.data;
                this.threadId = data.id;
                this.quota = data.quota;
                sessionStorage.setItem(this.storageKey(), data.id);
                await this.send(data.auto_prompt.content, data.preset, true);
            } catch (e) {
                this.error = 'Không kết nối được AI Tutor. Thử lại.';
            }
        },

        async send(content, preset = null, isAuto = false) {
            if (!this.threadId || this.streaming) return;
            content = (content || '').trim();
            if (!content) return;

            this.messages.push({ id: 'u' + Date.now(), role: 'user', content });
            const assistant = { id: 'pending', role: 'assistant', content: '', status: 'pending' };
            this.messages.push(assistant);
            this.streaming = true;
            this.error = '';
            this.scrollDown();

            this.ensureRealtime();

            try {
                const res = await fetch(this.cfg.routes.threads + '/' + this.threadId + '/messages', {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({ content, preset }),
                });
                const body = await res.json();
                if (!res.ok) {
                    this.streaming = false;
                    this.messages.pop();
                    this.handleGateError(res.status, body);
                    return;
                }
                const data = body.data;
                this.quota = data.quota;

                if (data.streaming) {
                    this.currentAssistantId = data.assistant_message_id;
                    assistant.serverId = data.assistant_message_id;
                    // Deltas arrive over Reverb; poll as a safety net if Echo is down.
                    this.pollUntilDone(data.assistant_message_id);
                } else {
                    assistant.content = data.assistant_message.content;
                    assistant.serverId = data.assistant_message.id;
                    assistant.citations = data.assistant_message.citations || [];
                    assistant.status = data.assistant_message.status;
                    this.streaming = false;
                    this.scrollDown();
                }
            } catch (e) {
                this.streaming = false;
                this.messages.pop();
                this.error = 'AI Tutor đang bận. Thử lại.';
            }
        },

        async pollUntilDone(messageId) {
            const maxAttempts = 60;
            for (let i = 0; i < maxAttempts; i++) {
                await new Promise((r) => setTimeout(r, 1000));
                if (!this.streaming || this.currentAssistantId !== messageId) return;
                try {
                    const res = await fetch(this.cfg.routes.threads + '/' + this.threadId, {
                        headers: this.headers(),
                    });
                    if (!res.ok) continue;
                    const body = await res.json();
                    const messages = (body.data && body.data.messages) || [];
                    const found = messages.find((m) => m.id === messageId);
                    if (!found) continue;
                    if (found.status === 'done' || found.status === 'stopped' || found.status === 'failed') {
                        const msg = this.messages.find((m) => m.serverId === messageId);
                        if (msg) {
                            msg.content = found.content || msg.content;
                            msg.citations = found.citations || [];
                            msg.status = found.status;
                        }
                        if (found.status === 'failed') {
                            this.error = this.error || 'AI Tutor gặp lỗi. Thử lại.';
                        }
                        this.streaming = false;
                        this.currentAssistantId = null;
                        this.scrollDown();
                        return;
                    }
                    // Mid-stream snapshot: show whatever content is already persisted.
                    if (found.content) {
                        const msg = this.messages.find((m) => m.serverId === messageId);
                        if (msg && (!msg.content || found.content.length > msg.content.length)) {
                            msg.content = found.content;
                            this.scrollDown();
                        }
                    }
                } catch (e) {}
            }
            if (this.streaming && this.currentAssistantId === messageId) {
                this.streaming = false;
                this.error = 'AI Tutor phản hồi chậm. Thử lại.';
                this.currentAssistantId = null;
            }
        },

        ensureRealtime() {
            if (this.subscribed || !this.cfg.userId || typeof window.enableMedlearnRealtime !== 'function') return;
            window.enableMedlearnRealtime().then(() => {
                if (this.subscribed || !window.Echo) return;
                this.subscribed = true;
                window.Echo.private('user.' + this.cfg.userId).listen('.ai.stream', (e) => this.onStream(e));
            }).catch(() => {});
        },

        onStream(e) {
            if (!this.currentAssistantId || e.message_id !== this.currentAssistantId) return;
            const msg = this.messages.find((m) => m.serverId === this.currentAssistantId);
            if (!msg) return;
            if (e.type === 'delta') {
                msg.content += e.delta;
                this.scrollDown();
            } else if (e.type === 'citation') {
                msg.citations = e.citations || [];
            } else if (e.type === 'done') {
                msg.content = e.content || msg.content;
                msg.citations = e.citations || msg.citations || [];
                msg.status = e.status || 'done';
                if (e.quota) this.quota = e.quota;
                this.streaming = false;
                this.currentAssistantId = null;
                this.scrollDown();
            } else if (e.type === 'error') {
                msg.status = 'failed';
                this.streaming = false;
                this.error = e.message || 'AI Tutor gặp lỗi. Thử lại.';
                this.currentAssistantId = null;
            }
        },

        async stop() {
            if (!this.streaming || !this.currentAssistantId) return;
            try {
                await fetch(this.cfg.routes.threads + '/' + this.threadId + '/stop', {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({ message_id: this.currentAssistantId }),
                });
            } catch (e) {}
        },

        async feedback(msg, vote) {
            if (!msg.serverId) return;
            msg.vote = vote;
            try {
                await fetch('/ai/messages/' + msg.serverId + '/feedback', {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({ vote }),
                });
            } catch (e) {}
        },

        copy(msg) {
            navigator.clipboard?.writeText(msg.content || '');
        },

        submitInput() {
            const v = this.input;
            this.input = '';
            this.send(v, null, false);
        },

        handleGateError(status, body) {
            if (status === 429) {
                this.error = (body.error && body.error.message) || 'Bạn đã hết lượt AI Tutor hôm nay.';
                if (body.error && body.error.details && body.error.details[0]) {
                    this.quota = body.error.details[0];
                }
            } else if (status === 403) {
                this.error = (body.error && body.error.message) || 'AI Tutor không khả dụng ở đây.';
            } else {
                this.error = (body.error && body.error.message) || 'Có lỗi xảy ra. Thử lại.';
            }
        },

        quotaLabel() {
            if (!this.quota) return '';
            if (this.quota.unlimited) return 'Không giới hạn';
            return 'Còn ' + this.quota.remaining + '/' + this.quota.limit + ' lượt hôm nay';
        },
    }"
    @keydown.escape.window="close()"
    @ai-tutor-open.window="if (!open) toggle()"
>
    {{-- Mobile FAB — sits above the session footer (z-50). Hidden on lg where
         the left toolbar already exposes "Hỏi AI Tutor". --}}
    <button type="button" @click="toggle()" x-show="!open" x-cloak
        class="fixed bottom-24 right-4 z-[60] flex items-center gap-2 rounded-full bg-[#0F766E] px-4 py-3 text-white shadow-lg transition hover:bg-[#0d655e] active:scale-95 lg:hidden"
        style="bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px));"
        aria-label="Hỏi AI Tutor">
        <span class="material-symbols-outlined text-[22px]">psychology</span>
        <span class="sr-only">Hỏi AI Tutor</span>
    </button>

    {{-- Overlay (mobile) --}}
    <div x-show="open" x-cloak x-transition.opacity @click="close()"
        class="fixed inset-0 z-[55] bg-black/40 md:hidden"></div>

    {{-- Drawer / sheet — above footer/FAB --}}
    <section x-show="open" x-cloak x-transition
        role="dialog" aria-label="AI Tutor"
        class="fixed inset-x-0 bottom-0 top-0 z-[70] flex w-full flex-col bg-white shadow-2xl md:inset-y-0 md:right-0 md:left-auto md:w-[420px] lg:w-[460px]">
        {{-- Header --}}
        <header class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
            <span class="material-symbols-outlined text-[#0F766E]">psychology</span>
            <div class="min-w-0 flex-1">
                <p class="truncate font-bold text-gray-900">AI Tutor</p>
                <p class="truncate text-xs text-gray-500" x-text="'Đang hỏi về: ' + cfg.label"></p>
            </div>
            <span class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-[#0F766E]" x-text="quotaLabel()"></span>
            <button type="button" @click="close()" class="rounded-full p-1.5 hover:bg-gray-100" aria-label="Đóng">
                <span class="material-symbols-outlined text-gray-500">close</span>
            </button>
        </header>

        {{-- Messages --}}
        <div x-ref="list" role="log" aria-live="polite" class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
            <template x-if="messages.length === 0 && !error">
                <div class="space-y-2">
                    <div class="h-3 w-2/3 animate-pulse rounded bg-gray-100"></div>
                    <div class="h-3 w-1/2 animate-pulse rounded bg-gray-100"></div>
                    <p class="pt-1 text-sm text-gray-400">Đang hỏi về câu đang xem…</p>
                </div>
            </template>

            <template x-for="msg in messages" :key="msg.id">
                <div>
                    <template x-if="msg.role === 'user'">
                        <div class="ml-auto max-w-[85%] rounded-2xl rounded-br-sm bg-[#0F766E] px-3 py-2 text-sm text-white" x-text="msg.content"></div>
                    </template>
                    <template x-if="msg.role === 'assistant'">
                        <div class="max-w-[92%] rounded-2xl rounded-bl-sm bg-gray-50 px-3 py-2 text-sm text-gray-800">
                            <template x-if="!msg.content && streaming">
                                <span class="inline-flex gap-1">
                                    <span class="size-1.5 animate-bounce rounded-full bg-gray-400"></span>
                                    <span class="size-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:.15s"></span>
                                    <span class="size-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:.3s"></span>
                                </span>
                            </template>
                            <div class="whitespace-pre-wrap break-words" x-text="msg.content"></div>
                            <template x-if="msg.citations && msg.citations.length">
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <template x-for="c in msg.citations" :key="c.id">
                                        <a :href="c.url || '#'" class="rounded-full bg-teal-50 px-2 py-0.5 text-xs text-[#0F766E]" x-text="c.label"></a>
                                    </template>
                                </div>
                            </template>
                            <template x-if="msg.status === 'done' || msg.status === 'stopped'">
                                <div class="mt-2 flex items-center gap-3 text-gray-400">
                                    <button type="button" @click="feedback(msg,'up')" :class="msg.vote==='up' && 'text-[#0F766E]'" class="hover:text-[#0F766E]" aria-label="Hữu ích">
                                        <span class="material-symbols-outlined text-[18px]">thumb_up</span>
                                    </button>
                                    <button type="button" @click="feedback(msg,'down')" :class="msg.vote==='down' && 'text-error'" class="hover:text-gray-600" aria-label="Chưa tốt">
                                        <span class="material-symbols-outlined text-[18px]">thumb_down</span>
                                    </button>
                                    <button type="button" @click="copy(msg)" class="hover:text-gray-600" aria-label="Sao chép">
                                        <span class="material-symbols-outlined text-[18px]">content_copy</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="error">
                <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    <span x-text="error"></span>
                    <button type="button" @click="autoStarted ? send(input || 'Thử lại giải thích câu này.', null, false) : autoStart()" class="ml-2 font-bold underline">Thử lại</button>
                </div>
            </template>
        </div>

        {{-- Follow-up chips --}}
        <div class="flex gap-2 overflow-x-auto border-t border-gray-100 px-4 py-2" x-show="threadId && !streaming">
            <template x-for="chip in ['Giải thích đơn giản hơn','Cho ví dụ lâm sàng','Điểm then chốt trên đề']" :key="chip">
                <button type="button" @click="send(chip, null, false)"
                    class="shrink-0 rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-600 hover:border-[#0F766E] hover:text-[#0F766E]" x-text="chip"></button>
            </template>
        </div>

        {{-- Input --}}
        <div class="flex items-end gap-2 border-t border-gray-200 p-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
            <textarea x-ref="input" x-model="input" rows="1" placeholder="Hỏi thêm về câu này…"
                @keydown.enter.prevent="submitInput()"
                class="flex-1 resize-none rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-[#0F766E] focus:outline-none"></textarea>
            <button type="button" x-show="!streaming" @click="submitInput()"
                class="flex size-10 items-center justify-center rounded-xl bg-[#0F766E] text-white hover:bg-[#0d655e]" aria-label="Gửi">
                <span class="material-symbols-outlined text-[20px]">arrow_upward</span>
            </button>
            <button type="button" x-show="streaming" @click="stop()"
                class="flex size-10 items-center justify-center rounded-xl border border-gray-300 text-gray-600 hover:bg-gray-50" aria-label="Dừng">
                <span class="material-symbols-outlined text-[20px]">stop</span>
            </button>
        </div>
    </section>
</div>
