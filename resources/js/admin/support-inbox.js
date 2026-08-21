/**
 * Admin support badge + list updates over Reverb only (no polling).
 * Seed pending IDs from SSR, then apply websocket deltas.
 */
export function bootAdminSupportInbox() {
    const enabled = document.body?.dataset?.supportInbox === '1';
    if (!enabled) {
        return;
    }

    const pending = new Set(
        String(document.body.dataset.supportPendingIds || '')
            .split(',')
            .map((value) => Number(value.trim()))
            .filter((id) => Number.isFinite(id) && id > 0),
    );

    const badgeNodes = () => document.querySelectorAll('[data-support-menu-badge]');

    const render = () => {
        const count = pending.size;
        badgeNodes().forEach((node) => {
            if (count >= 1) {
                node.textContent = count > 99 ? '99+' : String(count);
                node.classList.add('inline-flex');
                node.classList.remove('hidden');
            } else {
                node.textContent = '';
                node.classList.remove('inline-flex');
                node.classList.add('hidden');
            }
        });
    };

    const viewingId = () => {
        const raw = document.body.dataset.supportConversationId;
        return raw ? Number(raw) : null;
    };

    const applyEvent = (payload) => {
        const conversationId = Number(payload?.conversation_id ?? 0);
        if (!conversationId) {
            return;
        }

        const viewing = viewingId();
        if (viewing && viewing === conversationId) {
            pending.delete(conversationId);
            render();
            return;
        }

        if (payload?.needs_reply) {
            pending.add(conversationId);
        } else {
            pending.delete(conversationId);
        }

        render();

        if (document.body.dataset.supportInboxList === '1') {
            window.setTimeout(() => window.location.reload(), 200);
        }
    };

    window.addEventListener('support-inbox:seen', (event) => {
        const conversationId = Number(event.detail?.conversation_id ?? 0);
        if (!conversationId) {
            return;
        }
        pending.delete(conversationId);
        render();
    });

    let subscribed = false;
    const subscribe = () => {
        if (subscribed || !window.Echo) {
            return;
        }
        subscribed = true;

        const channel = window.Echo.private('support-admin');
        channel.listen('.message.created', applyEvent);
        channel.subscribed(() => {
            document.body.dataset.supportInboxLive = '1';
        });
        channel.error?.((error) => {
            console.warn('[support-inbox] channel error', error);
            subscribed = false;
        });
    };

    render();

    const start = () => {
        if (!window.enableMedlearnRealtime) {
            console.warn('[support-inbox] enableMedlearnRealtime missing');
            return;
        }

        window.enableMedlearnRealtime()
            .then(subscribe)
            .catch((error) => console.warn('[support-inbox] realtime failed', error));
        window.addEventListener('medlearn:echo-ready', subscribe, { once: true });
    };

    start();
}
