/** @typedef {{ id: string, question_id: string, target: string, option_id?: number|null, start: number, end: number, color: string }} TextMark */

export const MARK_COLORS = {
    yellow: { bg: 'rgba(250, 204, 21, 0.55)', label: 'Vàng' },
    green: { bg: 'rgba(74, 222, 128, 0.5)', label: 'Xanh lá' },
    pink: { bg: 'rgba(244, 114, 182, 0.5)', label: 'Hồng' },
    blue: { bg: 'rgba(96, 165, 250, 0.5)', label: 'Xanh dương' },
};

/**
 * Wrap a plain-text range inside an element (HTML-safe via text nodes).
 *
 * @param {HTMLElement} root
 * @param {number} start
 * @param {number} end
 * @param {(fragment: DocumentFragment) => HTMLElement} wrap
 */
function wrapTextRange(root, start, end, wrap) {
    if (end <= start) {
        return;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    /** @type {Array<{ node: Text, start: number, end: number }>} */
    const parts = [];
    let pos = 0;
    let node = walker.nextNode();
    while (node) {
        const text = node;
        const len = text.data.length;
        const nodeStart = pos;
        const nodeEnd = pos + len;
        if (nodeEnd > start && nodeStart < end) {
            parts.push({
                node: text,
                start: Math.max(start, nodeStart) - nodeStart,
                end: Math.min(end, nodeEnd) - nodeStart,
            });
        }
        pos = nodeEnd;
        node = walker.nextNode();
    }

    for (let i = parts.length - 1; i >= 0; i -= 1) {
        const part = parts[i];
        const { node: textNode, start: localStart, end: localEnd } = part;
        if (localStart > 0) {
            textNode.splitText(localStart);
        }
        const target = localStart > 0 ? textNode.nextSibling : textNode;
        if (! (target instanceof Text)) {
            continue;
        }
        const splitLen = localEnd - localStart;
        if (target.data.length > splitLen) {
            target.splitText(splitLen);
        }
        const mark = wrap(document.createDocumentFragment());
        target.parentNode?.insertBefore(mark, target);
        mark.appendChild(target);
    }
}

/**
 * @param {HTMLElement} el
 * @param {TextMark[]} marks
 */
export function applyMarksToElement(el, marks) {
    if (! (el instanceof HTMLElement) || ! Array.isArray(marks) || marks.length === 0) {
        return;
    }

    const sorted = [...marks].sort((a, b) => b.start - a.start);
    sorted.forEach((mark) => {
        const color = MARK_COLORS[mark.color] ?? MARK_COLORS.yellow;
        wrapTextRange(el, mark.start, mark.end, () => {
            const span = document.createElement('mark');
            span.dataset.markId = mark.id;
            span.className = 'live-text-mark';
            span.style.backgroundColor = color.bg;
            span.style.borderRadius = '0.15em';
            span.style.padding = '0 0.05em';
            span.style.boxDecorationBreak = 'clone';
            span.style.webkitBoxDecorationBreak = 'clone';

            return span;
        });
    });
}

/**
 * Resolve selection offsets relative to a content root's textContent.
 *
 * @param {HTMLElement} root
 * @returns {{ start: number, end: number }|null}
 */
export function selectionOffsetsIn(root) {
    const sel = window.getSelection();
    if (! sel || sel.isCollapsed || sel.rangeCount === 0) {
        return null;
    }
    const range = sel.getRangeAt(0);
    if (! root.contains(range.commonAncestorContainer)) {
        return null;
    }

    const pre = document.createRange();
    pre.selectNodeContents(root);
    pre.setEnd(range.startContainer, range.startOffset);
    const start = pre.toString().length;
    const end = start + range.toString().length;
    if (end <= start) {
        return null;
    }

    return { start, end };
}

/**
 * @param {TextMark[]} marks
 * @param {string} questionId
 * @param {'stem'|'option'} target
 * @param {number|null} [optionId]
 * @returns {TextMark[]}
 */
export function marksForTarget(marks, questionId, target, optionId = null) {
    return (marks ?? []).filter((m) => {
        if (String(m.question_id) !== String(questionId) || m.target !== target) {
            return false;
        }
        if (target === 'option') {
            return Number(m.option_id) === Number(optionId);
        }

        return true;
    });
}
