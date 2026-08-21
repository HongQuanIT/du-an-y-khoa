const typingBubbleHtml = (label) => `
<div class="max-w-[80%] rounded-2xl bg-surface-container px-4 py-3">
    <p class="mb-1 text-xs opacity-70">${label}</p>
    <p class="flex items-center gap-2 text-sm text-on-surface-variant">
        <span class="inline-flex gap-1" aria-hidden="true">
            <span class="size-1.5 animate-bounce rounded-full bg-on-surface-variant [animation-delay:-0.3s]"></span>
            <span class="size-1.5 animate-bounce rounded-full bg-on-surface-variant [animation-delay:-0.15s]"></span>
            <span class="size-1.5 animate-bounce rounded-full bg-on-surface-variant"></span>
        </span>
        Đang trả lời...
    </p>
</div>`;

const REMOTE_TYPING_TTL_MS = 3500;
const LOCAL_TYPING_IDLE_MS = 2000;
const LOCAL_TYPING_REFRESH_MS = 1500;

/**
 * Realtime typing via Echo whisper on a presence channel.
 *
 * @param {{ messagesEl: HTMLElement, selfSenderType: 'user'|'admin', remoteLabel?: string }} options
 */
export function createSupportTypingController({ messagesEl, selfSenderType, remoteLabel = 'Quản trị viên' }) {
    let typingNode = null;
    let aiTyping = false;
    let remoteTyping = false;
    let remoteTypingLabel = remoteLabel;
    let remoteHideTimer = null;
    let localHideTimer = null;
    let localRefreshTimer = null;
    let channel = null;
    let channelReady = false;
    let pendingTyping = null;

    const scroll = () => {
        if (messagesEl) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    };

    const removeTypingNode = () => {
        typingNode?.remove();
        typingNode = null;
    };

    const refreshTypingUi = () => {
        if (!messagesEl) {
            return;
        }

        if (!aiTyping && !remoteTyping) {
            removeTypingNode();
            return;
        }

        const label = aiTyping ? 'Trợ lý AI' : remoteTypingLabel;

        if (!typingNode) {
            typingNode = document.createElement('div');
            typingNode.className = 'flex justify-start';
            typingNode.dataset.supportTyping = 'true';
            messagesEl.append(typingNode);
        }

        typingNode.innerHTML = typingBubbleHtml(label);
        scroll();
    };

    const setAiTyping = (visible) => {
        aiTyping = Boolean(visible);
        refreshTypingUi();
    };

    const setRemoteTyping = (visible, label = remoteLabel) => {
        remoteTypingLabel = label;
        remoteTyping = Boolean(visible);
        refreshTypingUi();
    };

    const scheduleRemoteHide = () => {
        clearTimeout(remoteHideTimer);
        remoteHideTimer = setTimeout(() => setRemoteTyping(false), REMOTE_TYPING_TTL_MS);
    };

    const whisper = (typing) => {
        const value = Boolean(typing);

        if (!channel || !channelReady) {
            pendingTyping = value;
            return;
        }

        channel.whisper('typing', { sender_type: selfSenderType, typing: value });
    };

    const markReady = () => {
        if (channelReady) {
            return;
        }

        channelReady = true;

        if (pendingTyping !== null) {
            whisper(pendingTyping);
            pendingTyping = null;
        }
    };

    const bindChannel = (echoChannel) => {
        channel = echoChannel;
        channelReady = false;
        pendingTyping = null;

        // subscription may already be active by the time we bind
        if (echoChannel.subscription?.subscribed) {
            markReady();
        }

        if (typeof echoChannel.subscribed === 'function') {
            echoChannel.subscribed(markReady);
        }

        // Presence "here" also confirms membership for whispers.
        if (typeof echoChannel.here === 'function') {
            echoChannel.here(() => markReady());
        }

        // Hard fallback so typing is never stuck offline after a missed event.
        window.setTimeout(markReady, 800);

        echoChannel.listenForWhisper('typing', (payload) => {
            if (!payload || payload.sender_type === selfSenderType) {
                return;
            }

            if (payload.typing) {
                const label = payload.sender_type === 'admin' ? 'Quản trị viên' : remoteLabel;
                setRemoteTyping(true, label);
                scheduleRemoteHide();
            } else {
                clearTimeout(remoteHideTimer);
                setRemoteTyping(false);
            }
        });
    };

    const bindInput = (textarea) => {
        if (!textarea) {
            return;
        }

        const notifyTyping = () => {
            if (!textarea.value.trim()) {
                clearTimeout(localHideTimer);
                clearTimeout(localRefreshTimer);
                whisper(false);
                return;
            }

            whisper(true);
            clearTimeout(localHideTimer);
            localHideTimer = setTimeout(() => whisper(false), LOCAL_TYPING_IDLE_MS);

            // Keep refreshing so remote TTL does not expire while still typing.
            clearTimeout(localRefreshTimer);
            localRefreshTimer = setTimeout(function refresh() {
                if (!textarea.value.trim()) {
                    return;
                }
                whisper(true);
                localRefreshTimer = setTimeout(refresh, LOCAL_TYPING_REFRESH_MS);
            }, LOCAL_TYPING_REFRESH_MS);
        };

        textarea.addEventListener('input', notifyTyping);
        textarea.addEventListener('blur', () => {
            clearTimeout(localHideTimer);
            clearTimeout(localRefreshTimer);
            whisper(false);
        });
    };

    const clearBeforeRender = () => {
        removeTypingNode();
        if (aiTyping || remoteTyping) {
            refreshTypingUi();
        }
    };

    const destroy = () => {
        clearTimeout(remoteHideTimer);
        clearTimeout(localHideTimer);
        clearTimeout(localRefreshTimer);
        whisper(false);
        removeTypingNode();
        channel = null;
        channelReady = false;
        pendingTyping = null;
        aiTyping = false;
        remoteTyping = false;
    };

    return {
        bindChannel,
        bindInput,
        setAiTyping,
        setRemoteTyping,
        clearBeforeRender,
        destroy,
        getTypingNode: () => typingNode,
    };
}

window.MedlearnSupportTyping = { createSupportTypingController };
window.dispatchEvent(new Event('medlearn:support-typing-ready'));
