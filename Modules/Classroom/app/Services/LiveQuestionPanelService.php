<?php

declare(strict_types=1);

namespace Modules\Classroom\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Classroom\Models\LiveSession;
use Modules\QuestionBank\Models\Question;

final class LiveQuestionPanelService
{
    /**
     * @return array{
     *   total: int,
     *   index: int,
     *   show_answer: bool,
     *   question: array<string, mixed>|null,
     *   map: list<array{id: string, label: string}>
     * }
     */
    public function panel(LiveSession $session, ?User $user = null): array
    {
        $ids = $session->questionIds();
        $index = min(max(0, $session->current_question_index), max(0, count($ids) - 1));
        $questions = $this->questionsById($ids, $user);
        $currentId = $ids[$index] ?? null;

        return [
            'total' => count($ids),
            'index' => $index,
            'show_answer' => (bool) $session->show_answer,
            'question' => $currentId !== null ? $this->serializeQuestion($questions->get($currentId), $session->show_answer) : null,
            'map' => collect($ids)->values()->map(
                fn (string $id, int $i): array => [
                    'id' => $id,
                    'label' => (string) ($i + 1),
                ],
            )->all(),
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<string, Question>
     */
    private function questionsById(array $ids, ?User $user): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $query = Question::query()
            ->with(['options' => fn ($q) => $q->orderBy('order')])
            ->whereIn('id', $ids)
            ->where('status', 'published');

        if ($user !== null && ! $user->hasEntitlement(\App\Support\Enums\Entitlement::QbankFull->value)) {
            $query->where('is_free', true);
        }

        return $query->get()->keyBy(fn (Question $q) => (string) $q->getKey());
    }

    /** @return array<string, mixed>|null */
    private function serializeQuestion(?Question $question, bool $showAnswer): ?array
    {
        if ($question === null) {
            return null;
        }

        $options = $question->options->sortBy('order')->values()->map(
            fn ($opt): array => [
                'id' => (int) $opt->getKey(),
                'content' => (string) $opt->content,
                'is_correct' => $showAnswer ? (bool) $opt->is_correct : null,
            ],
        )->all();

        return [
            'id' => (string) $question->getKey(),
            'stem' => (string) $question->stem,
            'explanation' => $showAnswer ? (string) ($question->explanation ?? '') : null,
            'difficulty' => $question->difficulty->value,
            'options' => $options,
        ];
    }
}
