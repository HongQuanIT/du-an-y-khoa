/**
 * Build a learner-facing question panel from a moderator answer deck.
 *
 * @param {Array<Record<string, unknown>>} deck
 * @param {number} index
 * @param {number[]} revealedOptionIds
 * @param {Array<{id: string, label: string}>|null} map
 * @returns {Record<string, unknown>}
 */
export function panelFromDeck(deck, index, revealedOptionIds, map = null) {
    const total = Array.isArray(deck) ? deck.length : 0;
    const safeIndex = total > 0 ? Math.min(Math.max(0, index), total - 1) : 0;
    const full = total > 0 ? deck[safeIndex] : null;
    const revealed = new Set((revealedOptionIds ?? []).map(Number));
    const panelMap = map ?? (deck ?? []).map((q, i) => ({
        id: String(q?.id ?? i),
        label: String(i + 1),
    }));

    if (! full) {
        return {
            total,
            index: safeIndex,
            show_answer: false,
            question: null,
            map: panelMap,
            revealed_option_ids: [...revealed],
        };
    }

    let correctRevealed = false;
    const options = (full.options ?? []).map((opt) => {
        const id = Number(opt.id);
        const isRevealed = revealed.has(id);
        if (isRevealed && opt.is_correct === true) {
            correctRevealed = true;
        }

        return {
            id,
            content: opt.content ?? '',
            is_correct: isRevealed ? Boolean(opt.is_correct) : null,
            explanation: isRevealed ? (opt.explanation ?? null) : null,
        };
    });

    return {
        total,
        index: safeIndex,
        show_answer: false,
        map: panelMap,
        revealed_option_ids: [...revealed],
        question: {
            id: String(full.id),
            stem: full.stem ?? '',
            stem_image_url: full.stem_image_url ?? null,
            explanation: correctRevealed ? (full.explanation ?? null) : null,
            difficulty: full.difficulty,
            options,
        },
    };
}

/**
 * @param {number[]} current
 * @param {number} optionId
 * @returns {number[]}
 */
export function toggleRevealedOption(current, optionId) {
    const id = Number(optionId);
    const set = new Set((current ?? []).map(Number));
    if (set.has(id)) {
        set.delete(id);
    } else {
        set.add(id);
    }

    return [...set];
}

/**
 * Infer revealed option ids from a serialized panel question.
 *
 * @param {Record<string, unknown>|null|undefined} question
 * @returns {number[]}
 */
export function revealedIdsFromQuestion(question) {
    if (! question?.options) {
        return [];
    }

    return question.options
        .filter((opt) => opt.is_correct !== null && opt.is_correct !== undefined)
        .map((opt) => Number(opt.id))
        .filter((id) => Number.isFinite(id) && id > 0);
}
