import { createSupportTypingController } from './support-typing';

/**
 * Full-page learner support thread (`/support`).
 */
export function bootUserSupportThread() {
    const messagesEl = document.querySelector('[data-support-user-thread]');
    if (!messagesEl) {
        return;
    }

    const form = document.querySelector('[data-support-user-form]');
    const input = document.querySelector('[data-support-user-input]');
    const sendButton = document.getElementById('support-message-send');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const conversationId = messagesEl.dataset.conversationId;
    const messageUrl = messagesEl.dataset.messageUrl || form?.action;

    const typingController = createSupportTypingController({
        messagesEl,
        selfSenderType: 'user',
        remoteLabel: 'Quản trị viên',
    });
    typingController.bindInput(input);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    const appendMessage = (message) => {
        if (!message) {
            return;
        }
        if (message.id && messagesEl.querySelector(`[data-message-id="${message.id}"]`)) {
            return;
        }

        const row = document.createElement('div');
        row.className = `flex ${message.sender_type === 'user' ? 'justify-end' : 'justify-start'}`;
        if (message.id) {
            row.dataset.messageId = String(message.id);
        }
        const label = message.sender_type === 'ai' ? 'Trợ lý AI' : (message.sender_type === 'admin' ? 'Quản trị viên' : 'Bạn');
        const bubbleClass = message.sender_type === 'user' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface';
        row.innerHTML = `<div class="max-w-[80%] rounded-2xl px-4 py-3 ${bubbleClass}"><p class="mb-1 text-xs opacity-70">${label}</p><p class="whitespace-pre-wrap"></p></div>`;
        row.querySelector('p.whitespace-pre-wrap').textContent = message.body ?? '';
        messagesEl.insertBefore(row, typingController.getTypingNode() ?? null);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const renderMessages = (items) => {
        messagesEl.querySelectorAll(':scope > div:not([data-support-typing])').forEach((node) => node.remove());
        typingController.clearBeforeRender();
        items.forEach((message) => appendMessage(message));
    };

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!input || !messageUrl) {
            return;
        }

        const text = input.value.trim();
        if (!text) {
            return;
        }

        input.value = '';
        if (sendButton) {
            sendButton.disabled = true;
        }
        input.disabled = true;

        const previous = [...messagesEl.querySelectorAll(':scope > div:not([data-support-typing])')].map((node) => {
            const sender = node.querySelector('p.text-xs')?.textContent ?? '';
            const body = node.querySelector('p.whitespace-pre-wrap')?.textContent ?? '';
            let senderType = 'user';
            if (sender === 'Trợ lý AI') {
                senderType = 'ai';
            } else if (sender === 'Quản trị viên') {
                senderType = 'admin';
            }
            return { sender_type: senderType, body };
        });

        renderMessages([...previous, { sender_type: 'user', body: text }]);
        if (messagesEl.dataset.awaitingAi === '1') {
            typingController.setAiTyping(true);
        }

        try {
            const body = new FormData();
            body.append('message', text);
            body.append('_token', csrf);
            const response = await fetch(messageUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            if (!response.ok) {
                throw new Error('send_failed');
            }
            const data = await response.json();
            renderMessages(data.conversation.messages);
            messagesEl.dataset.awaitingAi = data.conversation.status === 'ai_active' ? '1' : '0';
        } catch {
            renderMessages(previous);
            input.value = text;
        } finally {
            typingController.setAiTyping(false);
            if (sendButton) {
                sendButton.disabled = false;
            }
            input.disabled = false;
        }
    });

    let subscribed = false;
    const subscribe = () => {
        if (subscribed || !window.Echo || !conversationId) {
            return;
        }
        subscribed = true;
        const echoChannel = window.Echo.join(`support-conversation.${conversationId}`);
        echoChannel.listen('.message.created', (payload) => {
            if (payload?.message) {
                appendMessage(payload.message);
                typingController.setRemoteTyping(false);
            }
        });
        typingController.bindChannel(echoChannel);
    };

    window.enableMedlearnRealtime?.()
        .then(subscribe)
        .catch(() => null);
    window.addEventListener('medlearn:echo-ready', subscribe, { once: true });
}
