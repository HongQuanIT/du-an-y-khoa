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
 * Standalone presenter window — host reference monitor without screen-share loop.
 */
const root = document.querySelector('[data-presenter-root]');
if (root instanceof HTMLElement) {
    const bootstrapUrl = root.dataset.bootstrapUrl ?? '';
    const questionUrl = root.dataset.questionUrl ?? '';
    const marksUrl = root.dataset.marksUrl
        ?? String(bootstrapUrl).replace('/bootstrap', '/marks');
    const sessionUuid = root.dataset.sessionUuid ?? '';
    const canModerate = root.dataset.canModerate === '1';
    let panelState = null;
    /** @type {Array<Record<string, unknown>>|null} */
    let questionDeck = null;
    /** @type {number[]} */
    let revealedOptionIds = [];
    /** @type {Array<Record<string, unknown>>} */
    let textMarks = [];
    let syncEpoch = 0;
    /** @type {HTMLElement|null} */
    let markToolbar = null;
    /** @type {number|null} */
    let pollTimer = null;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const renderStemImage = (container, url) => {
        if (! (container instanceof HTMLElement)) {
            return;
        }

        container.innerHTML = '';
        container.style.display = 'none';

        if (! url) {
            return;
        }

        const img = document.createElement('img');
        img.src = String(url);
        img.alt = 'Ảnh minh họa câu hỏi';
        img.style.width = '100%';
        img.style.maxHeight = '480px';
        img.style.objectFit = 'contain';
        img.style.borderRadius = '0.75rem';
        img.style.border = '1px solid #e5e7eb';
        container.appendChild(img);
        container.style.display = 'block';
    };

    const render = (panel) => {
        panelState = panel;
        if (Array.isArray(panel.revealed_option_ids)) {
            revealedOptionIds = panel.revealed_option_ids.map(Number);
        } else if (panel.question) {
            revealedOptionIds = revealedIdsFromQuestion(panel.question);
        }

        const questionId = panel.question ? String(panel.question.id) : null;
        const stem = root.querySelector('[data-q-stem]');
        const stemImage = root.querySelector('[data-q-stem-image]');
        const knowledge = root.querySelector('[data-q-knowledge]');
        const knowledgeContent = root.querySelector('[data-q-knowledge-content]');
        const options = root.querySelector('[data-q-options]');
        const explanation = root.querySelector('[data-q-explanation]');
        const label = root.querySelector('[data-q-index-label]');

        if (label) {
            label.textContent = panel.total > 0 ? `Câu ${panel.index + 1} / ${panel.total}` : '—';
        }
        if (! panel?.question) {
            if (stem) {
                stem.textContent = 'Chưa có câu hỏi.';
            }
            renderStemImage(stemImage, null);
            if (knowledge) {
                knowledge.style.display = 'none';
            }

            return;
        }
        if (stem) {
            stem.innerHTML = panel.question.stem ?? '';
            stem.classList.toggle('q-hints-revealed', Boolean(panel.question.hints_revealed));
            stem.dataset.qMarkTarget = 'stem';
            if (questionId) {
                applyMarksToElement(stem, marksForTarget(textMarks, questionId, 'stem'));
            }
        }
        renderStemImage(stemImage, panel.question.stem_image_url ?? null);
        if (knowledge && knowledgeContent) {
            if (panel.question.attending_tip) {
                knowledgeContent.innerHTML = panel.question.attending_tip;
                knowledge.style.display = 'block';
            } else {
                knowledgeContent.innerHTML = '';
                knowledge.style.display = 'none';
            }
        }
        if (options) {
            options.innerHTML = '';
            (panel.question.options ?? []).forEach((opt, i) => {
                const revealed = opt.is_correct !== null && opt.is_correct !== undefined;
                const isCorrect = revealed && opt.is_correct === true;
                const isWrong = revealed && opt.is_correct === false;
                const li = document.createElement('li');
                if (canModerate) {
                    li.dataset.qOptionId = String(opt.id ?? '');
                    li.style.cursor = 'pointer';
                    li.title = revealed ? 'Bấm lại để ẩn đáp án' : 'Bấm để hiện đáp án';
                }
                li.style.padding = '0';
                li.style.border = `1px solid ${isCorrect ? '#16a34a' : isWrong ? '#dc2626' : '#e5e7eb'}`;
                li.style.borderRadius = '0.5rem';
                li.style.overflow = 'hidden';
                li.style.background = isCorrect ? '#f0fdf4' : isWrong ? '#fef2f2' : '#fff';

                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.alignItems = 'flex-start';
                row.style.gap = '0.5rem';
                row.style.padding = '0.75rem';

                const letter = document.createElement('span');
                letter.style.fontWeight = '600';
                letter.textContent = `${String.fromCharCode(65 + i)}. `;

                const content = document.createElement('div');
                content.style.flex = '1';
                content.style.userSelect = 'text';
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
                    badge.style.fontSize = '0.75rem';
                    badge.style.fontWeight = '700';
                    badge.style.color = isCorrect ? '#16a34a' : '#dc2626';
                    badge.textContent = `${isCorrect ? 'Đáp án đúng' : 'Đáp án sai'} · Bấm lại để ẩn`;
                    row.appendChild(badge);
                }

                li.appendChild(row);

                if (revealed && opt.explanation) {
                    const note = document.createElement('div');
                    note.style.margin = '0';
                    note.style.padding = '0.625rem 0.75rem 0.625rem 1.75rem';
                    note.style.borderTop = '1px solid #e5e7eb';
                    note.style.fontSize = '0.8125rem';
                    note.style.lineHeight = '1.5';
                    note.style.color = '#4b5563';
                    note.style.userSelect = 'text';
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
                explanation.style.userSelect = 'text';
                explanation.style.display = 'block';
                if (questionId) {
                    applyMarksToElement(
                        explanation,
                        marksForTarget(textMarks, questionId, 'explanation'),
                    );
                }
            } else {
                explanation.style.display = 'none';
            }
        }
    };

    const renderLocal = (index) => {
        if (! questionDeck?.length) {
            return false;
        }
        render(panelFromDeck(questionDeck, index, revealedOptionIds, panelState?.map ?? null));

        return true;
    };

    const updateQuestion = async (index, { optionId = null } = {}) => {
        if (! canModerate) {
            return;
        }
        const body = { index };
        if (optionId !== null) {
            body.option_id = optionId;
        }

        const previousRevealed = [...revealedOptionIds];
        const previousIndex = panelState?.index ?? 0;
        const epoch = ++syncEpoch;
        const renderedOptimistically = Boolean(questionDeck?.length);

        if (questionDeck?.length) {
            if (optionId !== null) {
                revealedOptionIds = toggleRevealedOption(revealedOptionIds, optionId);
            }
            renderLocal(index);
        }

        try {
            const res = await fetch(questionUrl, {
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
            if (epoch !== syncEpoch) {
                return;
            }
            const serverRevealed = Array.isArray(data.revealed_option_ids)
                ? data.revealed_option_ids.map(Number)
                : revealedOptionIds;
            const responseIndex = Number(data.index ?? index);
            const sameRevealed = serverRevealed.length === revealedOptionIds.length
                && serverRevealed.every((id) => revealedOptionIds.includes(id));
            if (Array.isArray(data.revealed_option_ids)) {
                revealedOptionIds = serverRevealed;
            }
            if (questionDeck?.length) {
                if (! renderedOptimistically || responseIndex !== Number(index) || ! sameRevealed) {
                    renderLocal(responseIndex);
                }
            } else {
                render(data);
            }
        } catch (err) {
            console.error('[Presenter] question update', err);
            if (epoch !== syncEpoch) {
                return;
            }
            revealedOptionIds = previousRevealed;
            if (questionDeck?.length) {
                renderLocal(previousIndex);
            }
        }
    };

    const hideMarkToolbar = () => {
        if (markToolbar) {
            markToolbar.style.display = 'none';
        }
    };

    const ensureMarkToolbar = () => {
        if (markToolbar || ! canModerate) {
            return markToolbar;
        }
        const bar = document.createElement('div');
        bar.style.cssText = 'position:fixed;z-index:80;display:none;align-items:center;gap:0.25rem;border:1px solid #d1d5db;border-radius:0.5rem;background:#fff;padding:0.375rem 0.5rem;box-shadow:0 8px 24px rgba(0,0,0,.12)';
        bar.innerHTML = Object.entries(MARK_COLORS).map(([key, meta]) => (
            `<button type="button" data-mark-color="${key}" title="Tô ${meta.label}"
                style="width:1.5rem;height:1.5rem;border-radius:9999px;border:1px solid #d1d5db;background:${meta.bg};cursor:pointer"></button>`
        )).join('')
            + `<button type="button" data-mark-clear style="margin-left:0.25rem;border:0;background:transparent;font-size:11px;color:#6b7280;cursor:pointer">Xóa tô</button>`;
        document.body.appendChild(bar);
        markToolbar = bar;

        bar.addEventListener('mousedown', (e) => e.preventDefault());
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

    const showMarkToolbarAt = (x, y) => {
        const bar = ensureMarkToolbar();
        if (! bar) {
            return;
        }
        bar.style.display = 'flex';
        bar.style.left = `${Math.min(window.innerWidth - 180, Math.max(8, x))}px`;
        bar.style.top = `${Math.min(window.innerHeight - 48, Math.max(8, y))}px`;
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
            target: kind,
            optionId: (kind === 'option' || kind === 'explanation') && target.dataset.qMarkOptionId
                ? Number(target.dataset.qMarkOptionId)
                : null,
            ...offsets,
        };
    };

    const persistMarks = async (payload) => {
        const res = await fetch(marksUrl, {
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
            render(panelState);
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
        render(panelState);
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
            console.error('[Presenter] mark add', err);
            textMarks = textMarks.filter((m) => m.id !== optimistic.id);
            render(panelState);
        }
    };

    const clearMarksForCurrentQuestion = async () => {
        const questionId = panelState?.question?.id;
        if (! questionId) {
            return;
        }
        const previous = textMarks;
        textMarks = textMarks.filter((m) => String(m.question_id) !== String(questionId));
        render(panelState);
        hideMarkToolbar();
        try {
            await persistMarks({ action: 'clear', question_id: String(questionId) });
        } catch (err) {
            console.error('[Presenter] mark clear', err);
            textMarks = previous;
            render(panelState);
        }
    };

    const load = async () => {
        const res = await fetch(bootstrapUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (! res.ok) {
            return;
        }
        const json = await res.json();
        const data = json.data ?? json;
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
                renderLocal(data.question_panel.index ?? 0);
            } else {
                render(data.question_panel);
            }
        }
    };

    const applyQuestionEvent = (e) => {
        if (Array.isArray(e.revealed_option_ids)) {
            revealedOptionIds = e.revealed_option_ids.map(Number);
        }
        if (questionDeck?.length) {
            renderLocal(e.index ?? panelState?.index ?? 0);
        } else if (e.question) {
            render({
                total: e.total,
                index: e.index,
                show_answer: e.show_answer,
                question: e.question,
                map: panelState?.map ?? [],
                revealed_option_ids: e.revealed_option_ids ?? revealedOptionIds,
            });
        }
    };

    const subscribeEcho = () => {
        if (! window.Echo || ! sessionUuid) {
            return false;
        }

        window.Echo.private(`live-session.${sessionUuid}`)
            .listen('.question.changed', (e) => {
                if (canModerate && questionDeck?.length) {
                    if (Array.isArray(e.revealed_option_ids)) {
                        revealedOptionIds = e.revealed_option_ids.map(Number);
                    }
                    renderLocal(e.index ?? panelState?.index ?? 0);

                    return;
                }
                applyQuestionEvent(e);
            })
            .listen('.marks.updated', (e) => {
                textMarks = e.marks ?? [];
                if (panelState) {
                    render(panelState);
                }
            });

        return true;
    };

    root.querySelector('[data-q-prev]')?.addEventListener('click', () => {
        if (panelState) {
            updateQuestion(Math.max(0, panelState.index - 1));
        }
    });
    root.querySelector('[data-q-next]')?.addEventListener('click', () => {
        if (panelState) {
            updateQuestion(Math.min(panelState.total - 1, panelState.index + 1));
        }
    });
    root.querySelector('[data-q-clear-marks]')?.addEventListener('click', () => {
        void clearMarksForCurrentQuestion();
    });
    root.addEventListener('click', (e) => {
        const optionBtn = e.target instanceof Element ? e.target.closest('[data-q-option-id]') : null;
        if (! (optionBtn instanceof HTMLElement) || ! panelState) {
            return;
        }
        const sel = window.getSelection();
        if (sel && ! sel.isCollapsed && optionBtn.contains(sel.anchorNode)) {
            return;
        }
        const optionId = Number(optionBtn.dataset.qOptionId);
        if (Number.isFinite(optionId) && optionId > 0) {
            updateQuestion(panelState.index, { optionId });
        }
    });

    if (canModerate) {
        document.addEventListener('mouseup', (e) => {
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
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideMarkToolbar();
            }
        });
    }

    (async () => {
        await load();
        await ensureClassroomEcho();
        const realtimeConnected = subscribeEcho();
        // Echo primary for marks (<1s). Poll only as safety net.
        pollTimer = window.setInterval(load, realtimeConnected ? 30_000 : 5_000);
    })();

    window.addEventListener('pagehide', () => {
        if (pollTimer !== null) {
            window.clearInterval(pollTimer);
        }
        if (window.Echo && sessionUuid) {
            window.Echo.leave(`live-session.${sessionUuid}`);
        }
    });
}
