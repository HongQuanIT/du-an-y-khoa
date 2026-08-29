import { mountHlsPlayers } from './hls-player';
import {
    panelFromDeck,
    revealedIdsFromQuestion,
    toggleRevealedOption,
} from './question-deck';
import { ensureClassroomEcho } from './realtime';
import {
    MARK_COLORS,
    applyMarksToElement,
    marksForTarget,
    selectionOffsetsIn,
} from './text-marks';

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
    /** @type {Array<Record<string, unknown>>|null} */
    let questionDeck = null;
    /** @type {number[]} */
    let revealedOptionIds = [];
    /** @type {Array<Record<string, unknown>>} */
    let textMarks = [];
    let filterType = 'all';
    let chatMuted = Boolean(config.chat_muted);
    const canModerate = Boolean(config.can_moderate);
    const chatReadonly = Boolean(config.chat_readonly);
    const currentUserId = config.user_id != null ? Number(config.user_id) : null;

    /** @type {Array<Record<string, unknown>>} */
    let hands = [];
    let pollTimer = null;
    /** @type {number} */
    let questionSyncEpoch = 0;
    /** @type {HTMLElement|null} */
    let markToolbar = null;
    let stageTeach = false;

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
        const isOwnMessage = msg.type !== 'system'
            && currentUserId !== null
            && Number(msg.user?.id) === currentUserId;
        const row = document.createElement('div');
        row.dataset.messageId = String(msg.id ?? `tmp-${Date.now()}`);
        row.dataset.messageType = String(msg.type ?? 'chat');

        if (msg.type === 'system') {
            row.className = 'flex justify-center';
            const notice = document.createElement('div');
            notice.className = 'max-w-[90%] rounded-lg bg-primary/10 px-3 py-2 text-[13px] text-primary';
            notice.textContent = String(msg.body ?? '');
            row.appendChild(notice);
            return row;
        }

        row.className = `flex ${isOwnMessage ? 'justify-end' : 'justify-start'}`;

        const bubble = document.createElement('div');
        bubble.className = `max-w-[80%] rounded-2xl px-4 py-3 text-[13px] ${
            isOwnMessage
                ? 'bg-primary text-on-primary'
                : 'bg-surface-container text-on-surface'
        }`;

        const meta = document.createElement('div');
        meta.className = 'mb-1 flex items-center gap-1 text-[11px] opacity-70';
        meta.innerHTML = `
            <span>${escapeHtml(isOwnMessage ? 'Bạn' : (msg.user?.name ?? '—'))}</span>
            ${msg.type === 'question' ? '<span class="rounded bg-secondary/15 px-1">Hỏi</span>' : ''}
            ${msg.is_pinned ? '<span class="rounded bg-amber-100 px-1 text-amber-800">Ghim</span>' : ''}
        `;
        bubble.appendChild(meta);

        const body = document.createElement('p');
        body.className = 'whitespace-pre-wrap';
        body.textContent = String(msg.body ?? '');
        bubble.appendChild(body);
        row.appendChild(bubble);

        if (optimistic) {
            row.classList.add('opacity-60');
        }

        return row;
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

    const removeMessage = (id) => {
        allMessages = allMessages.filter((message) => message.id !== id);
        paintMessages();
    };

    const renderStemImage = (container, url) => {
        if (! (container instanceof HTMLElement)) {
            return;
        }

        container.innerHTML = '';
        container.classList.add('hidden');

        if (! url) {
            return;
        }

        const aside = document.createElement('aside');
        aside.className = 'overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest';
        const img = document.createElement('img');
        img.src = String(url);
        img.alt = 'Ảnh minh họa câu hỏi';
        img.className = 'mx-auto w-full max-h-[480px] object-contain';
        aside.appendChild(img);
        container.appendChild(aside);
        container.classList.remove('hidden');
    };

    const renderQuestionPanel = (panel) => {
        if (! panel || questionPanels.length === 0) {
            return;
        }
        panelState = panel;
        if (Array.isArray(panel.revealed_option_ids)) {
            revealedOptionIds = panel.revealed_option_ids.map(Number);
        } else if (panel.question) {
            revealedOptionIds = revealedIdsFromQuestion(panel.question);
        }

        const questionId = panel.question ? String(panel.question.id) : null;

        questionPanels.forEach((questionPanel) => {
            const stem = questionPanel.querySelector('[data-q-stem]');
            const stemImage = questionPanel.querySelector('[data-q-stem-image]');
            const options = questionPanel.querySelector('[data-q-options]');
            const explanation = questionPanel.querySelector('[data-q-explanation]');
            const label = questionPanel.querySelector('[data-q-index-label]');
            const map = questionPanel.querySelector('[data-q-map]');

            if (label) {
                label.textContent = panel.total > 0
                    ? `Câu ${panel.index + 1}/${panel.total}`
                    : 'Chưa có đề';
            }

            if (! panel.question) {
                if (stem) {
                    stem.textContent = 'Chưa có câu hỏi.';
                }
                renderStemImage(stemImage, null);
                if (options) {
                    options.innerHTML = '';
                }

                return;
            }

            if (stem) {
                stem.innerHTML = panel.question.stem ?? '';
                stem.dataset.qMarkTarget = 'stem';
                if (questionId) {
                    applyMarksToElement(stem, marksForTarget(textMarks, questionId, 'stem'));
                }
            }
            renderStemImage(stemImage, panel.question.stem_image_url ?? null);

            if (options) {
                options.innerHTML = '';
                (panel.question.options ?? []).forEach((opt, i) => {
                    const revealed = opt.is_correct !== null && opt.is_correct !== undefined;
                    const isCorrect = revealed && opt.is_correct === true;
                    const isWrong = revealed && opt.is_correct === false;
                    const li = document.createElement('li');
                    if (canModerate) {
                        li.dataset.qOptionId = String(opt.id ?? '');
                    }
                    li.className = 'overflow-hidden rounded-lg border bg-surface text-left text-sm text-on-surface'
                        + (isCorrect
                            ? ' border-success bg-success/5 font-medium text-success'
                            : isWrong
                                ? ' border-error bg-error/5 text-error'
                                : ' border-outline-variant')
                        + (canModerate ? ' cursor-pointer hover:border-primary/60' : '');

                    const row = document.createElement('div');
                    row.className = 'flex items-start gap-2 px-3 py-2';

                    const letter = document.createElement('span');
                    letter.className = 'font-medium';
                    letter.textContent = `${String.fromCharCode(65 + i)}. `;

                    const content = document.createElement('div');
                    content.className = 'prose prose-sm min-w-0 flex-1 select-text text-on-surface';
                    content.dataset.qMarkTarget = 'option';
                    content.dataset.qMarkOptionId = String(opt.id ?? '');
                    content.innerHTML = opt.content ?? '';
                    if (questionId) {
                        applyMarksToElement(
                            content,
                            marksForTarget(textMarks, questionId, 'option', Number(opt.id)),
                        );
                    }

                    row.append(letter, content);

                    if (isCorrect || isWrong) {
                        const badge = document.createElement('span');
                        badge.className = isCorrect
                            ? 'shrink-0 text-xs font-bold text-success'
                            : 'shrink-0 text-xs font-bold text-error';
                        badge.textContent = isCorrect ? 'Đáp án đúng' : 'Đáp án sai';
                        row.appendChild(badge);
                    }

                    li.appendChild(row);

                    if (revealed && opt.explanation) {
                        const note = document.createElement('div');
                        note.className = 'border-t border-outline-variant/60 px-3 py-2 pl-8 text-xs leading-relaxed text-on-surface-variant select-text';
                        note.dataset.qMarkTarget = 'explanation';
                        note.dataset.qMarkOptionId = String(opt.id ?? '');
                        note.innerHTML = opt.explanation;
                        if (questionId) {
                            applyMarksToElement(
                                note,
                                marksForTarget(textMarks, questionId, 'explanation', Number(opt.id)),
                            );
                        }
                        li.appendChild(note);
                    }

                    options.appendChild(li);
                });
            }

            if (explanation) {
                if (panel.question.explanation) {
                    explanation.innerHTML = panel.question.explanation;
                    explanation.dataset.qMarkTarget = 'explanation';
                    delete explanation.dataset.qMarkOptionId;
                    explanation.classList.add('select-text');
                    explanation.classList.remove('hidden');
                    if (questionId) {
                        applyMarksToElement(
                            explanation,
                            marksForTarget(textMarks, questionId, 'explanation'),
                        );
                    }
                } else {
                    explanation.classList.add('hidden');
                }
            }

            if (map && panel.map) {
                map.innerHTML = '';
                panel.map.forEach((item, i) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.dataset.qGoto = String(i);
                    btn.textContent = item.label;
                    btn.className = 'size-8 rounded text-xs '
                        + (i === panel.index ? 'bg-primary text-white' : 'bg-surface-container-low text-on-surface');
                    map.appendChild(btn);
                });
            }
        });
    };

    const renderFromLocalState = (index = panelState?.index ?? 0) => {
        if (! questionDeck || questionDeck.length === 0) {
            return false;
        }
        renderQuestionPanel(panelFromDeck(
            questionDeck,
            index,
            revealedOptionIds,
            panelState?.map ?? null,
        ));

        return true;
    };

    const updateQuestion = async (index, { optionId = null } = {}) => {
        const body = { index };
        if (optionId !== null) {
            body.option_id = optionId;
        }

        const previousRevealed = [...revealedOptionIds];
        const previousIndex = panelState?.index ?? 0;
        const epoch = ++questionSyncEpoch;

        if (canModerate && questionDeck?.length) {
            if (optionId !== null) {
                revealedOptionIds = toggleRevealedOption(revealedOptionIds, optionId);
            }
            renderFromLocalState(index);
        }

        try {
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
            if (! res.ok) {
                throw new Error('question update failed');
            }
            const json = await res.json();
            const data = json.data ?? json;
            if (epoch !== questionSyncEpoch) {
                return;
            }
            if (Array.isArray(data.revealed_option_ids)) {
                revealedOptionIds = data.revealed_option_ids.map(Number);
            }
            if (questionDeck?.length) {
                renderFromLocalState(data.index ?? index);
            } else {
                renderQuestionPanel(data);
            }
        } catch (err) {
            console.error('[LiveRoom] question update', err);
            if (epoch !== questionSyncEpoch) {
                return;
            }
            revealedOptionIds = previousRevealed;
            if (questionDeck?.length) {
                renderFromLocalState(previousIndex);
            }
        }
    };

    const ensureMarkToolbar = () => {
        if (markToolbar || ! canModerate) {
            return markToolbar;
        }
        const bar = document.createElement('div');
        bar.dataset.qMarkToolbar = '1';
        bar.className = 'fixed z-[80] hidden items-center gap-1 rounded-lg border border-outline-variant bg-surface px-2 py-1.5 shadow-lg';
        bar.innerHTML = Object.entries(MARK_COLORS).map(([key, meta]) => (
            `<button type="button" data-mark-color="${key}" title="Tô ${meta.label}"
                class="size-6 rounded-full border border-outline-variant"
                style="background:${meta.bg}"></button>`
        )).join('')
            + `<button type="button" data-mark-clear class="ml-1 rounded px-2 py-0.5 text-[11px] font-medium text-on-surface-variant hover:bg-surface-container-low">Xóa tô</button>`;
        document.body.appendChild(bar);
        markToolbar = bar;

        bar.addEventListener('mousedown', (e) => {
            // Keep selection while clicking toolbar.
            e.preventDefault();
        });

        bar.addEventListener('click', (e) => {
            const el = e.target instanceof Element ? e.target : null;
            if (! el) {
                return;
            }
            const colorBtn = el.closest('[data-mark-color]');
            if (colorBtn instanceof HTMLElement) {
                void addMarkFromSelection(colorBtn.dataset.markColor ?? 'yellow');

                return;
            }
            if (el.closest('[data-mark-clear]')) {
                void clearMarksForCurrentQuestion();
            }
        });

        return bar;
    };

    const hideMarkToolbar = () => {
        if (markToolbar) {
            markToolbar.classList.add('hidden');
            markToolbar.classList.remove('flex');
        }
    };

    const showMarkToolbarAt = (x, y) => {
        const bar = ensureMarkToolbar();
        if (! bar) {
            return;
        }
        bar.classList.remove('hidden');
        bar.classList.add('flex');
        const left = Math.min(window.innerWidth - 180, Math.max(8, x));
        const top = Math.min(window.innerHeight - 48, Math.max(8, y));
        bar.style.left = `${left}px`;
        bar.style.top = `${top}px`;
    };

    const resolveMarkTargetFromSelection = () => {
        const sel = window.getSelection();
        if (! sel || sel.rangeCount === 0) {
            return null;
        }
        const node = sel.getRangeAt(0).commonAncestorContainer;
        const el = node instanceof Element ? node : node.parentElement;
        const target = el?.closest?.('[data-q-mark-target]');
        if (! (target instanceof HTMLElement) || ! root.contains(target)) {
            return null;
        }
        const kind = target.dataset.qMarkTarget;
        if (kind !== 'stem' && kind !== 'option' && kind !== 'explanation') {
            return null;
        }
        const offsets = selectionOffsetsIn(target);
        if (! offsets) {
            return null;
        }

        return {
            el: target,
            target: kind,
            optionId: (kind === 'option' || kind === 'explanation') && target.dataset.qMarkOptionId
                ? Number(target.dataset.qMarkOptionId)
                : null,
            ...offsets,
        };
    };

    const persistMarks = async (payload) => {
        const url = apiUrls.marks ?? String(config.bootstrap_url).replace('/bootstrap', '/marks');
        const res = await fetch(url, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        if (! res.ok) {
            throw new Error('marks update failed');
        }
        const json = await res.json();
        textMarks = (json.data ?? json).marks ?? textMarks;
        if (panelState) {
            renderQuestionPanel(panelState);
        }
    };

    const addMarkFromSelection = async (color) => {
        const questionId = panelState?.question?.id;
        if (! questionId) {
            return;
        }
        const sel = resolveMarkTargetFromSelection();
        if (! sel) {
            hideMarkToolbar();

            return;
        }
        const optimistic = {
            id: `tmp-${Date.now()}`,
            question_id: String(questionId),
            target: sel.target,
            option_id: sel.optionId,
            start: sel.start,
            end: sel.end,
            color,
        };
        textMarks = [...textMarks, optimistic];
        renderQuestionPanel(panelState);
        hideMarkToolbar();
        window.getSelection()?.removeAllRanges();

        try {
            await persistMarks({
                action: 'add',
                question_id: String(questionId),
                target: sel.target,
                option_id: sel.optionId,
                start: sel.start,
                end: sel.end,
                color,
            });
        } catch (err) {
            console.error('[LiveRoom] mark add', err);
            textMarks = textMarks.filter((m) => m.id !== optimistic.id);
            renderQuestionPanel(panelState);
        }
    };

    const clearMarksForCurrentQuestion = async () => {
        const questionId = panelState?.question?.id;
        if (! questionId) {
            return;
        }
        const previous = textMarks;
        textMarks = textMarks.filter((m) => String(m.question_id) !== String(questionId));
        renderQuestionPanel(panelState);
        hideMarkToolbar();
        try {
            await persistMarks({ action: 'clear', question_id: String(questionId) });
        } catch (err) {
            console.error('[LiveRoom] mark clear', err);
            textMarks = previous;
            renderQuestionPanel(panelState);
        }
    };

    const showTeachBanner = () => {
        const banner = root.querySelector('[data-live-teach-banner]');
        if (banner instanceof HTMLElement) {
            banner.classList.toggle('hidden', ! stageTeach);
        }
    };

    const syncStageTeachButtons = () => {
        root.querySelectorAll('[data-live-stage-teach-label]').forEach((el) => {
            el.textContent = stageTeach ? 'Đang khung đề' : 'Khung đề';
        });
        root.querySelectorAll('[data-live-stage-teach-toggle]').forEach((btn) => {
            if (! (btn instanceof HTMLElement)) {
                return;
            }
            btn.classList.toggle('border-primary', stageTeach);
            btn.classList.toggle('text-primary', stageTeach);
            btn.setAttribute('aria-pressed', stageTeach ? 'true' : 'false');
        });
    };

    const applyStageTeach = (on, { syncMobile = true } = {}) => {
        stageTeach = Boolean(on);
        root.dataset.lkStageTeach = stageTeach ? '1' : '0';

        root.querySelectorAll('[data-live-stage-teach]').forEach((el) => {
            if (! (el instanceof HTMLElement)) {
                return;
            }
            el.classList.toggle('hidden', ! stageTeach);
            el.classList.toggle('flex', stageTeach);
        });

        const waiting = root.querySelector('[data-lk-waiting]');
        if (waiting instanceof HTMLElement && stageTeach) {
            waiting.classList.add('hidden');
        }

        showTeachBanner();
        syncStageTeachButtons();

        if (stageTeach && syncMobile && root._x_dataStack?.[0]) {
            // Mobile: stay on video tab so học viên thấy đề trong khung.
            root._x_dataStack[0].mobileTab = 'video';
        }

        root.dispatchEvent(new CustomEvent('live:stage-teach', { detail: { on: stageTeach } }));
    };

    const setStageTeach = async (next) => {
        if (! canModerate) {
            applyStageTeach(next);

            return;
        }
        applyStageTeach(next);
        const url = apiUrls.stage
            ?? String(config.bootstrap_url).replace('/bootstrap', '/stage');
        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ stage_teach: Boolean(next) }),
            });
            if (! res.ok) {
                throw new Error('stage update failed');
            }
        } catch (err) {
            console.error('[LiveRoom] stage', err);
            applyStageTeach(! next);
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

    const subscribeEcho = () => {
        if (! window.Echo || ! config.session_uuid) {
            return false;
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
                if (config.studio_mode && config.exit_url) {
                    window.location.href = config.exit_url;

                    return;
                }
                window.location.reload();
            })
            .listen('.question.changed', (e) => {
                if (canModerate && Number(e.actor_user_id) === currentUserId) {
                    // Host already applied optimistic UI; keep deck-driven state.
                    if (Array.isArray(e.revealed_option_ids)) {
                        revealedOptionIds = e.revealed_option_ids.map(Number);
                    }
                    if (questionDeck?.length) {
                        renderFromLocalState(e.index ?? panelState?.index ?? 0);

                        return;
                    }
                }
                if (Array.isArray(e.revealed_option_ids)) {
                    revealedOptionIds = e.revealed_option_ids.map(Number);
                }
                if (questionDeck?.length) {
                    renderFromLocalState(e.index ?? 0);
                } else {
                    renderQuestionPanel({
                        total: e.total,
                        index: e.index,
                        show_answer: e.show_answer,
                        question: e.question,
                        map: panelState?.map ?? [],
                        revealed_option_ids: e.revealed_option_ids ?? revealedOptionIds,
                    });
                }
            })
            .listen('.marks.updated', (e) => {
                textMarks = e.marks ?? [];
                if (panelState) {
                    renderQuestionPanel(panelState);
                }
            })
            .listen('.recording.ready', () => {
                window.location.reload();
            })
            .listen('.session.updated', (e) => {
                if (e.changes?.chat_muted !== undefined) {
                    applyChatMuted(e.changes.chat_muted);
                }
                if (e.changes?.stage_teach !== undefined) {
                    applyStageTeach(Boolean(e.changes.stage_teach));
                } else if (e.changes?.focus === 'questions') {
                    applyStageTeach(true);
                }
                if (Number(e.changes?.kicked_user_id) === currentUserId) {
                    window.location.href = e.changes?.redirect_url ?? '/classes';
                }
            });

        if (config.classroom_uuid) {
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
        }

        return true;
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
            const optimisticId = `tmp-${Date.now()}-${Math.random()}`;
            const optimistic = {
                id: optimisticId,
                body,
                type,
                user: { id: currentUserId, name: 'Bạn' },
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

            if (res.ok) {
                const json = await res.json();
                removeMessage(optimisticId);
                upsertMessage((json.data ?? json).message);
            } else {
                removeMessage(optimisticId);
                if (chatError instanceof HTMLElement) {
                    chatError.textContent = 'Không gửi được tin nhắn.';
                    chatError.classList.remove('hidden');
                }
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

        const gotoBtn = el.closest('[data-q-goto]');
        if (gotoBtn instanceof HTMLElement && root.contains(gotoBtn) && panelState) {
            const nextIndex = Number(gotoBtn.dataset.qGoto);
            if (Number.isFinite(nextIndex)) {
                updateQuestion(Math.max(0, Math.min(panelState.total - 1, nextIndex)));
            }

            return;
        }

        const qBtn = el.closest('[data-q-prev], [data-q-next]');
        if (qBtn && root.contains(qBtn) && panelState) {
            if (qBtn.matches('[data-q-prev]')) {
                updateQuestion(Math.max(0, panelState.index - 1));
            } else if (qBtn.matches('[data-q-next]')) {
                updateQuestion(Math.min(panelState.total - 1, panelState.index + 1));
            }

            return;
        }

        if (el.closest('[data-q-clear-marks]') && root.contains(el.closest('[data-q-clear-marks]'))) {
            void clearMarksForCurrentQuestion();

            return;
        }

        const optionBtn = el.closest('[data-q-option-id]');
        if (optionBtn instanceof HTMLElement && root.contains(optionBtn) && canModerate && panelState) {
            // Đang bôi chọn để tô màu → không sổ đáp án.
            const sel = window.getSelection();
            if (sel && ! sel.isCollapsed && optionBtn.contains(sel.anchorNode)) {
                return;
            }
            const optionId = Number(optionBtn.dataset.qOptionId);
            if (Number.isFinite(optionId) && optionId > 0) {
                updateQuestion(panelState.index, { optionId });
            }
        }
    });

    if (canModerate) {
        const onMarkMouseUp = (e) => {
            if (! (e.target instanceof Node) || ! root.contains(e.target)) {
                return;
            }
            window.setTimeout(() => {
                const sel = resolveMarkTargetFromSelection();
                if (! sel) {
                    hideMarkToolbar();

                    return;
                }
                showMarkToolbarAt(e.clientX + 8, e.clientY + 8);
            }, 10);
        };
        const onMarkKeyDown = (e) => {
            if (e.key === 'Escape') {
                hideMarkToolbar();
            }
        };
        document.addEventListener('mouseup', onMarkMouseUp);
        document.addEventListener('keydown', onMarkKeyDown);
        root._liveMarkCleanup = () => {
            document.removeEventListener('mouseup', onMarkMouseUp);
            document.removeEventListener('keydown', onMarkKeyDown);
        };
    }

    root.querySelector('[data-lk-teach]')?.addEventListener('click', async () => {
        if (config.can_moderate) {
            await setStageTeach(true);
        } else {
            applyStageTeach(true);
        }
    });

    root.querySelectorAll('[data-live-stage-teach-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (! canModerate) {
                return;
            }
            void setStageTeach(! stageTeach);
        });
    });

    syncFilterButtons();

    const applyBootstrap = (data) => {
        apiUrls = data.urls ?? apiUrls;
        if (data.session?.chat_muted !== undefined) {
            applyChatMuted(data.session.chat_muted);
        }
        if (data.session?.stage_teach !== undefined) {
            applyStageTeach(Boolean(data.session.stage_teach), { syncMobile: false });
        }
        loadMessages(data.messages);
        renderHands(data.hands ?? []);
        if (Array.isArray(data.question_deck) && data.question_deck.length > 0) {
            questionDeck = data.question_deck;
        }
        if (Array.isArray(data.text_marks)) {
            textMarks = data.text_marks;
        }
        if (data.question_panel) {
            if (Array.isArray(data.question_panel.revealed_option_ids)) {
                revealedOptionIds = data.question_panel.revealed_option_ids.map(Number);
            }
            if (questionDeck?.length) {
                renderFromLocalState(data.question_panel.index ?? 0);
            } else {
                renderQuestionPanel(data.question_panel);
            }
        }
        if (data.recording?.playback_url) {
            const hlsRoot = root.querySelector('[data-hls-root]');
            if (hlsRoot instanceof HTMLElement) {
                hlsRoot.dataset.hlsUrl = data.recording.playback_url;
            }
            mountHlsPlayers(root);
        }
    };

    const pollBootstrap = async () => {
        try {
            applyBootstrap(await fetchBootstrap());
        } catch (err) {
            console.error('[LiveRoom] poll', err);
        }
    };

    (async () => {
        try {
            applyBootstrap(await fetchBootstrap());
        } catch (err) {
            console.error('[LiveRoom] bootstrap', err);
        }

        // Echo loads async via app.js — must await or marks/chat fall back to slow poll.
        await ensureClassroomEcho();
        const realtimeConnected = subscribeEcho();
        // Reverb is primary (<1s). Poll is convergence-only if socket drops.
        pollTimer = window.setInterval(pollBootstrap, realtimeConnected ? 30_000 : 5_000);
    })();

    return () => {
        if (pollTimer !== null) {
            window.clearInterval(pollTimer);
        }
        if (typeof root._liveMarkCleanup === 'function') {
            root._liveMarkCleanup();
            delete root._liveMarkCleanup;
        }
        if (markToolbar) {
            markToolbar.remove();
            markToolbar = null;
        }
        if (window.Echo && config.session_uuid) {
            window.Echo.leave(`live-session.${config.session_uuid}`);
        }
        if (window.Echo && config.classroom_uuid) {
            window.Echo.leave(`classroom.${config.classroom_uuid}`);
        }
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
