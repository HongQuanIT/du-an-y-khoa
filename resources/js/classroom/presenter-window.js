/**
 * Standalone presenter window — host reference monitor without screen-share loop.
 */
const root = document.querySelector('[data-presenter-root]');
if (root instanceof HTMLElement) {
    const bootstrapUrl = root.dataset.bootstrapUrl ?? '';
    const questionUrl = root.dataset.questionUrl ?? '';
    const canModerate = root.dataset.canModerate === '1';
    let panelState = null;

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
        const stem = root.querySelector('[data-q-stem]');
        const stemImage = root.querySelector('[data-q-stem-image]');
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

            return;
        }
        if (stem) {
            stem.innerHTML = panel.question.stem ?? '';
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
                    li.style.cursor = 'pointer';
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

                const label = document.createElement('span');
                label.style.fontWeight = '600';
                label.textContent = `${String.fromCharCode(65 + i)}. `;

                const content = document.createElement('div');
                content.style.flex = '1';
                content.innerHTML = opt.content ?? '';

                row.append(label, content);

                if (isCorrect || isWrong) {
                    const badge = document.createElement('span');
                    badge.style.fontSize = '0.75rem';
                    badge.style.fontWeight = '700';
                    badge.style.color = isCorrect ? '#16a34a' : '#dc2626';
                    badge.textContent = isCorrect ? 'Đáp án đúng' : 'Đáp án sai';
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
                    note.innerHTML = opt.explanation;
                    li.appendChild(note);
                }

                options.appendChild(li);
            });
        }
        if (explanation) {
            if (panel.question.explanation) {
                explanation.innerHTML = panel.question.explanation;
                explanation.style.display = 'block';
            } else {
                explanation.style.display = 'none';
            }
        }
    };

    const updateQuestion = async (index, { optionId = null } = {}) => {
        if (! canModerate) {
            return;
        }
        const body = { index };
        if (optionId !== null) {
            body.option_id = optionId;
        }
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
        if (res.ok) {
            const json = await res.json();
            render(json.data ?? json);
        }
    };

    const load = async () => {
        const res = await fetch(bootstrapUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (res.ok) {
            const json = await res.json();
            render((json.data ?? json).question_panel);
        }
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
    root.addEventListener('click', (e) => {
        const optionBtn = e.target instanceof Element ? e.target.closest('[data-q-option-id]') : null;
        if (! (optionBtn instanceof HTMLElement) || ! panelState) {
            return;
        }
        const optionId = Number(optionBtn.dataset.qOptionId);
        if (Number.isFinite(optionId) && optionId > 0) {
            updateQuestion(panelState.index, { optionId });
        }
    });

    load();
    setInterval(load, 5000);
}
