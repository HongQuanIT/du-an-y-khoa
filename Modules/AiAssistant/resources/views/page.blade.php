<x-layouts.app title="AI Tutor">
    <div class="mx-auto flex h-[calc(100vh-var(--spacing-header-height))] max-w-3xl flex-col px-4 py-6"
        x-data="{
            threadId: null,
            userId: {{ (int) (auth()->id() ?? 0) }},
            csrf: @js(csrf_token()),
            input: '',
            streaming: false,
            error: '',
            messages: [],
            currentAssistantId: null,
            subscribed: false,
            quota: @js($quota),
            headers() {
                return { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrf,'X-Requested-With':'XMLHttpRequest' };
            },
            scrollDown() { this.$nextTick(() => { const l=this.$refs.list; if(l) l.scrollTop=l.scrollHeight; }); },
            async ensureThread() {
                if (this.threadId) return;
                const res = await fetch('/ai/threads', { method:'POST', headers:this.headers(), body: JSON.stringify({ auto_start:false }) });
                const body = await res.json();
                if (res.ok) { this.threadId = body.data.id; this.quota = body.data.quota; }
                else { this.error = (body.error && body.error.message) || 'Không tạo được phiên.'; }
            },
            ensureRealtime() {
                if (this.subscribed || !this.userId || typeof window.enableMedlearnRealtime !== 'function') return;
                window.enableMedlearnRealtime().then(() => {
                    if (this.subscribed || !window.Echo) return;
                    this.subscribed = true;
                    window.Echo.private('user.' + this.userId).listen('.ai.stream', (e) => this.onStream(e));
                }).catch(() => {});
            },
            onStream(e) {
                if (!this.currentAssistantId || e.message_id !== this.currentAssistantId) return;
                const msg = this.messages.find((m) => m.serverId === this.currentAssistantId);
                if (!msg) return;
                if (e.type==='delta') { msg.content += e.delta; this.scrollDown(); }
                else if (e.type==='done') { msg.content = e.content || msg.content; if(e.quota) this.quota=e.quota; this.streaming=false; this.currentAssistantId=null; this.scrollDown(); }
                else if (e.type==='error') { this.streaming=false; this.error=e.message||'Lỗi.'; this.currentAssistantId=null; }
            },
            async submit() {
                const content = this.input.trim();
                if (!content || this.streaming) return;
                this.input='';
                await this.ensureThread();
                if (!this.threadId) return;
                this.messages.push({ id:'u'+Date.now(), role:'user', content });
                const assistant = { id:'pending', role:'assistant', content:'' };
                this.messages.push(assistant);
                this.streaming=true; this.error=''; this.scrollDown();
                this.ensureRealtime();
                try {
                    const res = await fetch('/ai/threads/'+this.threadId+'/messages', { method:'POST', headers:this.headers(), body: JSON.stringify({ content }) });
                    const body = await res.json();
                    if (!res.ok) { this.streaming=false; this.messages.pop(); this.error=(body.error&&body.error.message)||'Lỗi.'; return; }
                    const data = body.data; this.quota = data.quota;
                    if (data.streaming) { this.currentAssistantId = data.assistant_message_id; assistant.serverId = data.assistant_message_id; }
                    else { assistant.content = data.assistant_message.content; assistant.serverId = data.assistant_message.id; this.streaming=false; this.scrollDown(); }
                } catch (e) { this.streaming=false; this.messages.pop(); this.error='AI Tutor đang bận. Thử lại.'; }
            },
        }">
        <div class="mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#0F766E]">psychology</span>
            <h1 class="text-lg font-bold text-gray-900">AI Tutor</h1>
            <span class="ml-auto rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-[#0F766E]"
                x-text="quota && quota.unlimited ? 'Không giới hạn' : (quota ? 'Còn ' + quota.remaining + '/' + quota.limit + ' lượt' : '')"></span>
        </div>

        <div x-ref="list" class="flex-1 space-y-4 overflow-y-auto rounded-xl border border-gray-100 bg-white p-4">
            <template x-if="messages.length === 0">
                <div class="grid gap-2 sm:grid-cols-2">
                    <template x-for="s in ['Cơ chế bù trừ trong toan chuyển hóa?','Phân biệt STEMI và NSTEMI','Tiêu chuẩn chẩn đoán đái tháo đường','Kháng sinh kinh nghiệm viêm phổi cộng đồng']" :key="s">
                        <button type="button" @click="input=s; submit()" class="rounded-lg border border-gray-200 px-3 py-2 text-left text-sm text-gray-700 hover:border-[#0F766E]" x-text="s"></button>
                    </template>
                </div>
            </template>
            <template x-for="msg in messages" :key="msg.id">
                <div>
                    <template x-if="msg.role==='user'">
                        <div class="ml-auto max-w-[85%] rounded-2xl rounded-br-sm bg-[#0F766E] px-3 py-2 text-sm text-white" x-text="msg.content"></div>
                    </template>
                    <template x-if="msg.role==='assistant'">
                        <div class="max-w-[92%] whitespace-pre-wrap break-words rounded-2xl rounded-bl-sm bg-gray-50 px-3 py-2 text-sm text-gray-800" x-text="msg.content || (streaming ? '…' : '')"></div>
                    </template>
                </div>
            </template>
            <template x-if="error">
                <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="error"></div>
            </template>
        </div>

        <div class="mt-3 flex items-end gap-2">
            <textarea x-model="input" rows="1" placeholder="Hỏi một khái niệm y khoa…" @keydown.enter.prevent="submit()"
                class="flex-1 resize-none rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-[#0F766E] focus:outline-none"></textarea>
            <button type="button" @click="submit()" :disabled="streaming"
                class="flex size-10 items-center justify-center rounded-xl bg-[#0F766E] text-white hover:bg-[#0d655e] disabled:opacity-50" aria-label="Gửi">
                <span class="material-symbols-outlined text-[20px]">arrow_upward</span>
            </button>
        </div>
        <p class="mt-2 text-center text-xs text-gray-400">Chỉ phục vụ học tập, không thay thế ý kiến chuyên môn.</p>
    </div>
</x-layouts.app>
