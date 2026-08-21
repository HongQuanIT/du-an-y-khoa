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

    const render = (panel) => {
        panelState = panel;
        const stem = root.querySelector('[data-q-stem]');
        const options = root.querySelector('[data-q-options]');
        const explanation = root.querySelector('[data-q-explanation]');
        const label = root.querySelector('[data-q-index-label]');
        const toggle = root.querySelector('[data-q-toggle-answer]');

        if (label) {
            label.textContent = panel.total > 0 ? `Câu ${panel.index + 1} / ${panel.total}` : '—';
        }
        if (! panel?.question) {
            if (stem) {
                stem.textContent = 'Chưa có câu hỏi.';
            }

            return;
        }
        if (stem) {
            stem.innerHTML = panel.question.stem ?? '';
        }
        if (options) {
            options.innerHTML = '';
            (panel.question.options ?? []).forEach((opt, i) => {
                const li = document.createElement('li');
                li.style.padding = '0.75rem';
                li.style.border = '1px solid #e5e7eb';
                li.style.borderRadius = '0.5rem';
                if (opt.is_correct) {
                    li.style.borderColor = '#2563eb';
                    li.style.background = '#eff6ff';
                }
                const label = document.createElement('span');
                label.style.fontWeight = '600';
                label.textContent = `${String.fromCharCode(65 + i)}. `;

                const content = document.createElement('span');
                content.innerHTML = opt.content ?? '';

                li.append(label, content);
                options.appendChild(li);
            });
        }
        if (explanation) {
            if (panel.show_answer && panel.question.explanation) {
                explanation.innerHTML = panel.question.explanation;
                explanation.style.display = 'block';
            } else {
                explanation.style.display = 'none';
            }
        }
        if (toggle) {
            toggle.textContent = panel.show_answer ? 'Ẩn đáp án' : 'Hiện đáp án';
        }
    };

    const updateQuestion = async (index, showAnswer = null) => {
        if (! canModerate) {
            return;
        }
        const body = { index };
        if (showAnswer !== null) {
            body.show_answer = showAnswer;
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
    root.querySelector('[data-q-toggle-answer]')?.addEventListener('click', () => {
        if (panelState) {
            updateQuestion(panelState.index, ! panelState.show_answer);
        }
    });

    load();
    setInterval(load, 5000);
}
