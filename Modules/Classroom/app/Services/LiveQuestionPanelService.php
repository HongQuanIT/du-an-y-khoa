<?php

declare(strict_types=1);

namespace Modules\Classroom\Services;

use App\Support\Html\SafeHtml;
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
    public function panel(LiveSession $session): array
    {
        $ids = $session->questionIds();
        $index = min(max(0, $session->current_question_index), max(0, count($ids) - 1));
        $questions = $this->questionsById($ids);
        $currentId = $ids[$index] ?? null;

        return [
            'total' => count($ids),
            'index' => $index,
            'show_answer' => (bool) $session->show_answer,
            'question' => $currentId !== null
                ? $this->serializeQuestion(
                    $questions->get($currentId),
                    $session->revealedOptionIds(),
                )
                : null,
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
    private function questionsById(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        // Quyền xem được bảo vệ ở Classroom; mọi thành viên live phải nhận cùng một câu.
        return Question::query()
            ->with(['options' => fn ($q) => $q->orderBy('order')])
            ->whereIn('id', $ids)
            ->where('status', 'published')
            ->get()
            ->keyBy(fn (Question $q) => (string) $q->getKey());
    }

    /**
     * @param  list<int>  $revealedOptionIds
     * @return array<string, mixed>|null
     */
    private function serializeQuestion(?Question $question, array $revealedOptionIds): ?array
    {
        if ($question === null) {
            return null;
        }

        $revealedLookup = array_fill_keys($revealedOptionIds, true);
        $correctRevealed = false;

        $options = $question->options->sortBy('order')->values()->map(
            function ($opt) use ($revealedLookup, &$correctRevealed): array {
                $optionId = (int) $opt->getKey();
                $revealed = isset($revealedLookup[$optionId]);
                if ($revealed && $opt->is_correct) {
                    $correctRevealed = true;
                }

                return [
                    'id' => $optionId,
                    'content' => SafeHtml::forDisplay((string) $opt->content),
                    'is_correct' => $revealed ? (bool) $opt->is_correct : null,
                    'explanation' => $revealed
                        ? SafeHtml::forDisplay((string) ($opt->explanation ?? ''))
                        : null,
                ];
            },
        )->all();

        return [
            'id' => (string) $question->getKey(),
            'stem' => SafeHtml::forDisplay((string) $question->stem),
            'stem_image_url' => $question->stemImageUrl(),
            'explanation' => $correctRevealed
                ? SafeHtml::forDisplay((string) ($question->explanation ?? ''))
                : null,
            'difficulty' => $question->difficulty->value,
            'options' => $options,
        ];
    }
}
