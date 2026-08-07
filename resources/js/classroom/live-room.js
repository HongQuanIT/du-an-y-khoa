import { mountHlsPlayers } from './hls-player';

/**
 * Classroom live room: Echo chat, question panel, moderation hooks.
 * @param {HTMLElement} root
 */
export function mountLiveRoom(root) {
    if (! (root instanceof HTMLElement) || root.dataset.liveMounted === '1') {
        return () => {};
    }
    root.dataset.liveMounted = '1';

    /** @type {Record<string, unknown>} */
    let config = {};
    /** @type {Record<string, string>} */
    let apiUrls = {};
    try {
        config = JSON.parse(root.dataset.liveConfig ?? '{}');
    } catch {
        config = {};
    }

    const messagesEls = root.querySelectorAll('[data-live-messages]');
    const chatForms = root.querySelectorAll('[data-live-chat-form]');
    const questionPanels = root.querySelectorAll('[data-live-question-panel]');

    /** @type {Array<Record<string, unknown>>} */
    let allMessages = [];
    let panelState = null;
    let filterType = 'all';
    let chatMuted = Boolean(config.chat_muted);
    const canModerate = Boolean(config.can_moderate);
    const chatReadonly = Boolean(config.chat_readonly);
    const currentUserId = config.user_id != null ? Number(config.user_id) : null;

    /** @type {Array<Record<string, unknown>>} */
    let hands = [];

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const fetchBootstrap = async () => {
        const res = await fetch(String(config.bootstrap_url), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (! res.ok) {
            throw new Error('Bootstrap failed');
        }
        const json = await res.json();
        return json.data ?? json;
    };

    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const buildMessageEl = (msg, optimistic = false) => {
        const wrap = document.createElement('div');
        wrap.dataset.messageId = String(msg.id ?? `tmp-${Date.now()}`);
        wrap.dataset.messageType = String(msg.type ?? 'chat');
        wrap.className = msg.type === 'system'
            ? 'rounded-lg bg-primary/10 px-3 py-2 text-sm text-primary'
            : 'rounded-lg bg-surface-container-low px-3 py-2 text-sm';

        if (msg.type !== 'system') {
            const meta = document.createElement('div');
            meta.className = 'mb-0.5 flex items-center gap-2 text-xs text-on-surface-variant';
            meta.innerHTML = `
                <span class="font-medium text-on-surface">${escapeHtml(msg.user?.name ?? '—')}</span>
                ${msg.type === 'question' ? '<span class="rounded bg-secondary/15 px-1 text-secondary">Hỏi</span>' : ''}
                ${msg.is_pinned ? '<span class="rounded bg-amber-100 px-1 text-amber-800">Ghim</span>' : ''}
            `;
            wrap.appendChild(meta);
        }

        const body = document.createElement('p');
        body.className = 'whitespace-pre-wrap text-on-surface';
        body.textContent = String(msg.body ?? '');
        wrap.appendChild(body);

        if (optimistic) {
            wrap.classList.add('opacity-60');
        }

        return wrap;
    };

    const visibleMessages = () => filterType === 'question'
        ? allMessages.filter((m) => m.type === 'question')
        : allMessages;

    const paintMessages = () => {
        const list = visibleMessages();
        messagesEls.forEach((messagesEl) => {
            messagesEl.innerHTML = '';
            list.forEach((msg) => {
                messagesEl.appendChild(buildMessageEl(msg));
            });
            messagesEl.scrollTop = messagesEl.scrollHeight;
        });
    };

    const syncFilterButtons = () => {
        root.querySelectorAll('[data-live-chat-filter] [data-filter]').forEach((btn) => {
            if (! (btn instanceof HTMLElement)) {
                return;
            }
            const active = btn.dataset.filter === filterType;
            btn.classList.toggle('bg-surface', active);
            btn.classList.toggle('shadow-sm', active);
            btn.classList.toggle('text-on-surface', active);
            btn.classList.toggle('text-on-surface-variant', ! active);
        });
    };

    const setFilter = (next) => {
        filterType = next === 'question' ? 'question' : 'all';
        syncFilterButtons();
        paintMessages();
    };

    const upsertMessage = (msg, optimistic = false) => {
        if (msg.id != null) {
            const idx = allMessages.findIndex((m) => m.id === msg.id);
            if (idx >= 0) {
                allMessages[idx] = msg;
            } else {
                allMessages.push(msg);
            }
        } else if (optimistic) {
            allMessages.push(msg);
        }
        paintMessages();
    };

    const loadMessages = (messages) => {
        allMessages = Array.isArray(messages) ? [...messages] : [];
        paintMessages();
    };

    const renderQuestionPanel = (panel) => {
        if (! panel || questionPanels.length === 0) {
            return;
        }
        panelState = panel;

        questionPanels.forEach((questionPanel) => {
            const stem = questionPanel.querySelector('[data-q-stem]');
            const options = questionPanel.querySelector('[data-q-options]');
            const explanation = questionPanel.querySelector('[data-q-explanation]');
            const label = questionPanel.querySelector('[data-q-index-label]');
            const map = questionPanel.querySelector('[data-q-map]');
            const toggleAnswer = questionPanel.querySelector('[data-q-toggle-answer]');

            if (label) {
                label.textContent = panel.total > 0
                    ? `Câu ${panel.index + 1}/${panel.total}`
                    : 'Chưa có đề';
            }

            if (! panel.question) {
                if (stem) {
                    stem.textContent = 'Chưa có câu hỏi.';
                }
                if (options) {
                    options.innerHTML = '';
                }

                return;
            }

            if (stem) {
                stem.textContent = panel.question.stem;
            }

            if (options) {
                options.innerHTML = '';
                (panel.question.options ?? []).forEach((opt, i) => {
                    const li = document.createElement('li');
                    li.className = 'rounded-lg border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface'
                        + (opt.is_correct ? ' border-primary bg-primary/5 font-medium text-primary' : '');
                    li.textContent = `${String.fromCharCode(65 + i)}. ${opt.content}`;
                    options.appendChild(li);
                });
            }

            if (explanation) {
                if (panel.show_answer && panel.question.explanation) {
                    explanation.textContent = panel.question.explanation;
                    explanation.classList.remove('hidden');
                } else {
                    explanation.classList.add('hidden');
                }
            }

            if (toggleAnswer) {
                toggleAnswer.textContent = panel.show_answer ? 'Ẩn đáp án' : 'Hiện đáp án';
            }

            if (map && panel.map) {
                map.innerHTML = '';
                panel.map.forEach((item, i) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = item.label;
                    btn.className = 'size-8 rounded text-xs '
                        + (i === panel.index ? 'bg-primary text-white' : 'bg-surface-container-low text-on-surface');
                    btn.addEventListener('click', () => updateQuestion(i));
                    map.appendChild(btn);
                });
            }
        });
    };

    const updateQuestion = async (index, showAnswer = null) => {
        const body = { index };
        if (showAnswer !== null) {
            body.show_answer = showAnswer;
        }
        const res = await fetch(apiUrls.question ?? String(config.bootstrap_url).replace('/bootstrap', '/question'), {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        if (res.ok) {
            const json = await res.json();
            renderQuestionPanel(json.data ?? json);
        }
    };

    const switchToQuestionsTab = () => {
        root.querySelector('[data-live-tab="questions"]')?.click();
        if (root._x_dataStack?.[0]) {
            root._x_dataStack[0].mobileTab = 'questions';
        }
    };

    const showTeachBanner = () => {
        const banner = root.querySelector('[data-live-teach-banner]');
        if (banner instanceof HTMLElement) {
            banner.classList.remove('hidden');
        }
    };

    /** @type {AudioContext|null} */
    let audioCtx = null;
    const ensureAudio = () => {
        if (! audioCtx) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (! Ctx) {
                return null;
            }
            audioCtx = new Ctx();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume().catch(() => {});
        }

        return audioCtx;
    };

    const tone = (freq, start, dur, type = 'sine', gain = 0.08) => {
        const ctx = ensureAudio();
        if (! ctx) {
            return;
        }
        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        osc.type = type;
        osc.frequency.value = freq;
        g.gain.setValueAtTime(0.0001, start);
        g.gain.exponentialRampToValueAtTime(gain, start + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, start + dur);
        osc.connect(g);
        g.connect(ctx.destination);
        osc.start(start);
        osc.stop(start + dur + 0.02);
    };

    const playRaiseHandSound = () => {
        const ctx = ensureAudio();
        if (! ctx) {
            return;
        }
        const t = ctx.currentTime;
        tone(880, t, 0.12, 'triangle', 0.09);
        tone(1174.7, t + 0.1, 0.16, 'triangle', 0.08);
        tone(1396.9, t + 0.22, 0.22, 'sine', 0.07);
    };

    const playReactionSound = (type) => {
        const ctx = ensureAudio();
        if (! ctx) {
            return;
        }
        const t = ctx.currentTime;
        if (type === 'heart') {
            tone(523.25, t, 0.1, 'sine', 0.06);
            tone(659.25, t + 0.08, 0.14, 'sine', 0.05);
        } else {
            tone(392, t, 0.08, 'square', 0.035);
            tone(587.33, t + 0.07, 0.12, 'triangle', 0.05);
        }
    };

    const spawnReaction = (type, burst = 1) => {
        const layers = root.querySelectorAll('[data-live-reactions]');
        if (layers.length === 0) {
            return;
        }
        const emoji = type === 'like' ? '👍' : '❤️';
        const count = Math.max(1, Math.min(burst, 3));
        layers.forEach((layer) => {
            if (! (layer instanceof HTMLElement)) {
                return;
            }
            for (let i = 0; i < count; i += 1) {
                const el = document.createElement('span');
                el.className = 'live-reaction-bubble';
                el.textContent = emoji;
                const x = 58 + Math.random() * 30;
                const drift = (Math.random() * 80) - 40;
                const driftMid = drift * 0.4;
                el.style.setProperty('--live-x', `${x}%`);
                el.style.setProperty('--live-drift', `${drift}px`);
                el.style.setProperty('--live-drift-mid', `${driftMid}px`);
                el.style.animationDelay = `${i * 90}ms`;
                layer.appendChild(el);
                window.setTimeout(() => el.remove(), 3200 + i * 90);
            }
        });
    };

    const sendReaction = async (type) => {
        ensureAudio();
        spawnReaction(type, 2);
        playReactionSound(type);

        const url = apiUrls.react ?? String(config.bootstrap_url).replace('/bootstrap', '/react');
        await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ type }),
        });
    };

    const dismissHandUrl = (handId) => {
        const raiseUrl = apiUrls.raise_hand ?? String(config.bootstrap_url).replace('/bootstrap', '/raise-hand');

        return raiseUrl.replace(/\/raise-hand\/?$/, `/hands/${handId}/dismiss`);
    };

    const syncRaiseHandButtons = () => {
        const mine = hands.some((h) => Number(h.user?.id) === currentUserId);
        root.querySelectorAll('[data-live-raise-hand]').forEach((btn) => {
            if (! (btn instanceof HTMLElement)) {
                return;
            }
            btn.dataset.raised = mine ? '1' : '0';
            btn.textContent = mine ? 'Hạ tay' : 'Giơ tay';
            btn.classList.toggle('border-amber-400', mine);
            btn.classList.toggle('bg-amber-50', mine);
            btn.classList.toggle('text-amber-900', mine);
        });
    };

    const renderHands = (nextHands) => {
        hands = Array.isArray(nextHands) ? [...nextHands] : [];
        syncRaiseHandButtons();

        root.querySelectorAll('[data-live-hands]').forEach((wrap) => {
            if (! (wrap instanceof HTMLElement)) {
                return;
            }
            const list = wrap.querySelector('[data-live-hands-list]');
            const count = wrap.querySelector('[data-live-hands-count]');
            if (count) {
                count.textContent = String(hands.length);
            }
            wrap.classList.toggle('hidden', hands.length === 0);
            if (! list) {
                return;
            }
            list.innerHTML = '';
            hands.forEach((hand, index) => {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between gap-2 rounded-md bg-white/80 px-2 py-1.5 text-xs';
                const name = document.createElement('span');
                name.className = 'min-w-0 truncate font-medium text-on-surface';
                name.textContent = `${index + 1}. ${hand.user?.name ?? 'Học viên'}`;
                li.appendChild(name);

                if (canModerate) {
                    const dismiss = document.createElement('button');
                    dismiss.type = 'button';
                    dismiss.dataset.dismissHand = String(hand.id);
                    dismiss.className = 'shrink-0 rounded border border-outline-variant px-2 py-0.5 text-[11px] text-on-surface hover:bg-surface-container-low';
                    dismiss.textContent = 'Đã gọi';
                    li.appendChild(dismiss);
                }
                list.appendChild(li);
            });
        });
    };

    const toggleRaiseHand = async () => {
        ensureAudio();
        const url = apiUrls.raise_hand ?? String(config.bootstrap_url).replace('/bootstrap', '/raise-hand');
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (! res.ok) {
            return;
        }
        const json = await res.json();
        const data = json.data ?? json;
        if (Array.isArray(data.hands)) {
            renderHands(data.hands);
        }
        if (data.raised) {
            playRaiseHandSound();
        }
    };

    const dismissHand = async (handId) => {
        const res = await fetch(dismissHandUrl(handId), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (! res.ok) {
            return;
        }
        const json = await res.json();
        const data = json.data ?? json;
        if (Array.isArray(data.hands)) {
            renderHands(data.hands);
        }
    };

    const applyChatMuted = (muted) => {
        chatMuted = Boolean(muted);

        root.querySelectorAll('[data-live-mute-chat]').forEach((btn) => {
            if (! (btn instanceof HTMLElement)) {
                return;
            }
            btn.dataset.chatMuted = chatMuted ? '1' : '0';
            btn.textContent = chatMuted ? 'Bật chat' : 'Tắt chat';
        });

        root.querySelectorAll('[data-live-chat-status]').forEach((el) => {
            if (! (el instanceof HTMLElement) || chatReadonly) {
                return;
            }
            if (chatMuted) {
                el.textContent = canModerate
                    ? 'Chat đang tắt — học viên không gửi được tin'
                    : 'Chat đang tắt';
                el.classList.remove('hidden');
                el.classList.add('text-error');
                el.classList.remove('text-on-surface-variant');
            } else {
                el.textContent = '';
                el.classList.add('hidden');
            }
        });

        root.querySelectorAll('[data-live-chat-compose]').forEach((el) => {
            if (! (el instanceof HTMLElement) || chatReadonly) {
                return;
            }
            // Host vẫn gửi được khi mute; học viên ẩn form.
            el.classList.toggle('hidden', chatMuted && ! canModerate);
        });

        root.querySelectorAll('[data-live-chat-muted-hint]').forEach((el) => {
            if (! (el instanceof HTMLElement) || chatReadonly) {
                return;
            }
            el.classList.toggle('hidden', canModerate || ! chatMuted);
        });
    };

    const toggleMuteChat = async () => {
        const url = apiUrls.mute_chat ?? String(config.bootstrap_url).replace('/bootstrap', '/mute-chat');
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (! res.ok) {
            return;
        }
        const json = await res.json();
        const next = Boolean((json.data ?? json).chat_muted);
        applyChatMuted(next);
    };

    const focusQuestions = async () => {
        const url = apiUrls.focus_questions
            ?? String(config.bootstrap_url).replace('/bootstrap', '/focus-questions');
        await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            credentials: 'same-origin',
        });
    };

    const subscribeEcho = () => {
        if (! window.Echo || ! config.session_uuid) {
            return;
        }

        window.Echo.private(`live-session.${config.session_uuid}`)
            .listen('.message.created', (e) => {
                upsertMessage(e.message);
            })
            .listen('.hands.updated', (e) => {
                renderHands(e.hands ?? []);
                if (e.action === 'raised' && Number(e.actor_user_id) !== currentUserId) {
                    playRaiseHandSound();
                }
            })
            .listen('.reaction.sent', (e) => {
                if (Number(e.user?.id) === currentUserId) {
                    return;
                }
                spawnReaction(e.type === 'like' ? 'like' : 'heart', 2);
                playReactionSound(e.type === 'like' ? 'like' : 'heart');
            })
            .listen('.session.ended', () => {
                window.location.reload();
            })
            .listen('.question.changed', (e) => {
                renderQuestionPanel({
                    total: e.total,
                    index: e.index,
                    show_answer: e.show_answer,
                    question: e.question,
                    map: panelState?.map ?? [],
                });
            })
            .listen('.recording.ready', () => {
                window.location.reload();
            })
            .listen('.session.updated', (e) => {
                if (e.changes?.chat_muted !== undefined) {
                    applyChatMuted(e.changes.chat_muted);
                }
                if (e.changes?.focus === 'questions') {
                    switchToQuestionsTab();
                }
            });

        window.Echo.join(`classroom.${config.classroom_uuid}`)
            .here((users) => {
                const el = document.querySelector('[data-live-viewer-count]');
                if (el) {
                    el.textContent = `${users.length} người`;
                }
                const lkCount = root.querySelector('[data-lk-count]');
                if (lkCount) {
                    lkCount.textContent = `${users.length} người`;
                }
            })
            .joining(() => {
                const lkCount = root.querySelector('[data-lk-count]');
                if (lkCount) {
                    const n = parseInt(lkCount.textContent ?? '1', 10) + 1;
                    lkCount.textContent = `${n} người`;
                }
            })
            .leaving(() => {
                const lkCount = root.querySelector('[data-lk-count]');
                if (lkCount) {
                    const n = Math.max(1, parseInt(lkCount.textContent ?? '1', 10) - 1);
                    lkCount.textContent = `${n} người`;
                }
            });
    };

    chatForms.forEach((chatForm) => {
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const chatInput = chatForm.querySelector('[data-live-msg-input]');
            const chatType = chatForm.querySelector('[data-live-msg-type]');
            const chatError = chatForm.querySelector('[data-live-chat-error]');
            if (! (chatInput instanceof HTMLInputElement)) {
                return;
            }
            const body = chatInput.value.trim();
            if (! body) {
                return;
            }

            const type = chatType instanceof HTMLSelectElement ? chatType.value : 'chat';
            const optimistic = {
                id: null,
                body,
                type,
                user: { name: 'Bạn' },
            };
            upsertMessage(optimistic, true);
            chatInput.value = '';
            if (chatError instanceof HTMLElement) {
                chatError.classList.add('hidden');
            }

            const msgUrl = apiUrls.messages ?? String(config.bootstrap_url).replace('/bootstrap', '/messages');
            const res = await fetch(msgUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ body, type }),
            });

            if (! res.ok && chatError instanceof HTMLElement) {
                chatError.textContent = 'Không gửi được tin nhắn.';
                chatError.classList.remove('hidden');
            }
        });
    });

    root.addEventListener('click', (e) => {
        const el = e.target instanceof Element ? e.target : null;
        if (! el) {
            return;
        }

        const filterBtn = el.closest('[data-live-chat-filter] [data-filter]');
        if (filterBtn instanceof HTMLElement && root.contains(filterBtn)) {
            setFilter(filterBtn.dataset.filter ?? 'all');

            return;
        }

        if (el.closest('[data-live-raise-hand]') && root.contains(el.closest('[data-live-raise-hand]'))) {
            toggleRaiseHand();

            return;
        }

        const reactBtn = el.closest('[data-live-react]');
        if (reactBtn instanceof HTMLElement && root.contains(reactBtn)) {
            const type = reactBtn.dataset.liveReact === 'like' ? 'like' : 'heart';
            sendReaction(type);

            return;
        }

        const dismissBtn = el.closest('[data-dismiss-hand]');
        if (dismissBtn instanceof HTMLElement && root.contains(dismissBtn)) {
            dismissHand(dismissBtn.dataset.dismissHand);

            return;
        }

        if (el.closest('[data-live-mute-chat]') && root.contains(el.closest('[data-live-mute-chat]'))) {
            toggleMuteChat();

            return;
        }

        const qBtn = el.closest('[data-q-prev], [data-q-next], [data-q-toggle-answer]');
        if (qBtn && root.contains(qBtn) && panelState) {
            if (qBtn.matches('[data-q-prev]')) {
                updateQuestion(Math.max(0, panelState.index - 1));
            } else if (qBtn.matches('[data-q-next]')) {
                updateQuestion(Math.min(panelState.total - 1, panelState.index + 1));
            } else if (qBtn.matches('[data-q-toggle-answer]')) {
                updateQuestion(panelState.index, ! panelState.show_answer);
            }
        }
    });

    root.querySelector('[data-lk-teach]')?.addEventListener('click', async () => {
        switchToQuestionsTab();
        showTeachBanner();
        if (config.can_moderate) {
            await focusQuestions();
        }
    });

    root.querySelectorAll('[data-live-presenter-popout]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const url = apiUrls.presenter;
            if (! url) {
                return;
            }
            window.open(url, 'live-presenter', 'width=720,height=900,menubar=no,toolbar=no');
        });
    });

    syncFilterButtons();

    (async () => {
        try {
            const data = await fetchBootstrap();
            apiUrls = data.urls ?? {};
            if (data.session?.chat_muted !== undefined) {
                applyChatMuted(data.session.chat_muted);
            }
            loadMessages(data.messages);
            renderHands(data.hands ?? []);
            renderQuestionPanel(data.question_panel);
            if (data.recording?.playback_url) {
                const hlsRoot = root.querySelector('[data-hls-root]');
                if (hlsRoot instanceof HTMLElement) {
                    hlsRoot.dataset.hlsUrl = data.recording.playback_url;
                }
                mountHlsPlayers(root);
            }
            subscribeEcho();
        } catch (err) {
            console.error('[LiveRoom] bootstrap', err);
            setInterval(async () => {
                try {
                    const data = await fetchBootstrap();
                    loadMessages(data.messages);
                } catch {
                    // ignore poll errors
                }
            }, 10_000);
        }
    })();

    return () => {
        delete root.dataset.liveMounted;
    };
}

export function bootLiveRooms(root = document) {
    root.querySelectorAll('[data-live-room]').forEach((el) => {
        if (el instanceof HTMLElement) {
            mountLiveRoom(el);
        }
    });
}
