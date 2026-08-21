import { createSupportTypingController } from '../support-typing';

const GROUP_GAP_SECONDS = 120;

const formatMessageTime = (iso) => {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (value) => String(value).padStart(2, '0');
    return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
};

/**
 * Admin support conversation thread (detail page).
 */
export function bootAdminSupportThread() {
    const root = document.querySelector('[data-support-admin-thread]');
    if (!root) {
        return;
    }

    const messagesEl = root.querySelector('[data-support-messages]');
    const form = root.querySelector('[data-support-message-form]');
    const input = root.querySelector('[data-support-message-input]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const conversationId = root.dataset.conversationId;
    const userLabel = root.dataset.userLabel || 'Người dùng';
    const seenUrl = root.dataset.seenUrl;
    const messageUrl = form?.action;

    if (!messagesEl || !conversationId) {
        return;
    }

    const typingController = createSupportTypingController({
        messagesEl,
        selfSenderType: 'admin',
        remoteLabel: userLabel,
    });
    typingController.bindInput(input);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    const updateBadges = (count) => {
        const value = Math.max(0, Number(count) || 0);
        document.querySelectorAll('[data-support-menu-badge]').forEach((node) => {
            if (value >= 1) {
                node.textContent = value > 99 ? '99+' : String(value);
                node.classList.add('inline-flex');
                node.classList.remove('hidden');
            } else {
                node.textContent = '';
                node.classList.remove('inline-flex');
                node.classList.add('hidden');
            }
        });
    };

    const lastMessageNode = () => {
        const nodes = messagesEl.querySelectorAll(':scope > [data-message-id]');
        return nodes.length ? nodes[nodes.length - 1] : null;
    };

    const shouldShowTime = (message) => {
        const previous = lastMessageNode();
        if (!previous) {
            return true;
        }

        const sameSender = previous.dataset.senderType === message.sender_type
            && String(previous.dataset.senderId || '') === String(message.sender_id ?? '');
        if (!sameSender) {
            return true;
        }

        const prevAt = Date.parse(previous.dataset.createdAt || '');
        const nextAt = Date.parse(message.created_at || '');
        if (!Number.isFinite(prevAt) || !Number.isFinite(nextAt)) {
            return true;
        }

        return Math.abs(nextAt - prevAt) / 1000 >= GROUP_GAP_SECONDS;
    };

    const appendMessage = (message) => {
        if (!message?.id) {
            return;
        }
        if (messagesEl.querySelector(`[data-message-id="${message.id}"]`)) {
            return;
        }

        const mine = message.sender_type === 'admin';
        const label = message.sender_type === 'ai' ? 'Trợ lý AI' : (mine ? 'Bạn' : userLabel);
        const showTime = shouldShowTime(message);
        const timeLabel = formatMessageTime(message.created_at);
        const row = document.createElement('div');
        row.className = `flex ${mine ? 'justify-end' : 'justify-start'}`;
        row.dataset.messageId = String(message.id);
        row.dataset.senderType = message.sender_type || '';
        row.dataset.senderId = message.sender_id != null ? String(message.sender_id) : '';
        row.dataset.createdAt = message.created_at || '';

        const stack = document.createElement('div');
        stack.className = `flex max-w-[80%] flex-col ${mine ? 'items-end' : 'items-start'} gap-1`;

        if (showTime && timeLabel) {
            const timeEl = document.createElement('time');
            timeEl.className = 'px-1 text-[11px] text-on-surface-variant';
            timeEl.dateTime = message.created_at || '';
            timeEl.textContent = timeLabel;
            stack.append(timeEl);
        }

        const bubble = document.createElement('div');
        bubble.className = `rounded-2xl px-4 py-3 ${mine ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface'}`;
        const by = document.createElement('p');
        by.className = 'mb-1 text-xs opacity-70';
        by.textContent = label;
        const body = document.createElement('p');
        body.className = 'whitespace-pre-wrap';
        body.textContent = message.body ?? '';
        bubble.append(by, body);
        stack.append(bubble);
        row.append(stack);
        messagesEl.insertBefore(row, typingController.getTypingNode() ?? null);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const syncSeen = async () => {
        if (!seenUrl) {
            return;
        }

        try {
            const body = new FormData();
            body.append('_token', csrf);
            const response = await fetch(seenUrl, {
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
                return;
            }
            const data = await response.json();
            updateBadges(data.count);
            window.dispatchEvent(new CustomEvent('support-inbox:seen', {
                detail: { conversation_id: Number(conversationId) },
            }));
        } catch {
            // ignore
        }
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

        typingController.setRemoteTyping(false);
        input.value = '';
        input.disabled = true;

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
            (data.conversation?.messages ?? []).forEach(appendMessage);
            await syncSeen();
        } catch {
            input.value = text;
        } finally {
            input.disabled = false;
            input.focus();
        }
    });

    let subscribed = false;
    const subscribe = () => {
        if (subscribed || !window.Echo) {
            return;
        }
        subscribed = true;

        const echoChannel = window.Echo.join(`support-conversation.${conversationId}`);
        echoChannel.listen('.message.created', (payload) => {
            appendMessage(payload?.message);
            typingController.setRemoteTyping(false);
            if (payload?.message?.sender_type !== 'admin') {
                syncSeen();
            }
        });
        typingController.bindChannel(echoChannel);
    };

    window.enableMedlearnRealtime?.()
        .then(subscribe)
        .catch(() => null);
    window.addEventListener('medlearn:echo-ready', subscribe, { once: true });
}
