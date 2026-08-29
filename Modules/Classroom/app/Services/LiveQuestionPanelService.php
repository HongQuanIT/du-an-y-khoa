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
     *   map: list<array{id: string, label: string}>,
     *   revealed_option_ids: list<int>
     * }
     */
    public function panel(LiveSession $session): array
    {
        $ids = $session->questionIds();
        $index = min(max(0, $session->current_question_index), max(0, count($ids) - 1));
        $questions = $this->questionsById($ids);
        $currentId = $ids[$index] ?? null;
        $revealed = $session->revealedOptionIds();

        return [
            'total' => count($ids),
            'index' => $index,
            'show_answer' => (bool) $session->show_answer,
            'question' => $currentId !== null
                ? $this->serializeQuestion(
                    $questions->get($currentId),
                    $revealed,
                )
                : null,
            'map' => $this->map($ids),
            'revealed_option_ids' => $revealed,
        ];
    }

    /**
     * Full answer key for moderators only — enables optimistic reveal/nav on the client.
     *
     * @return list<array<string, mixed>>
     */
    public function deck(LiveSession $session): array
    {
        $ids = $session->questionIds();
        if ($ids === []) {
            return [];
        }

        $questions = $this->questionsById($ids);

        return collect($ids)
            ->map(function (string $id) use ($questions): ?array {
                $question = $questions->get($id);
                if ($question === null) {
                    return null;
                }

                return $this->serializeQuestion($question, revealAll: true);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{id: string, label: string}>
     */
    public function map(array $ids): array
    {
        return collect($ids)->values()->map(
            fn (string $id, int $i): array => [
                'id' => $id,
                'label' => (string) ($i + 1),
            ],
        )->all();
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
    private function serializeQuestion(
        ?Question $question,
        array $revealedOptionIds = [],
        bool $revealAll = false,
    ): ?array {
        if ($question === null) {
            return null;
        }

        $revealedLookup = array_fill_keys($revealedOptionIds, true);
        $correctRevealed = false;

        $options = $question->options->sortBy('order')->values()->map(
            function ($opt) use ($revealedLookup, $revealAll, &$correctRevealed): array {
                $optionId = (int) $opt->getKey();
                $revealed = $revealAll || isset($revealedLookup[$optionId]);
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
            'explanation' => ($revealAll || $correctRevealed)
                ? SafeHtml::forDisplay((string) ($question->explanation ?? ''))
                : null,
            'difficulty' => $question->difficulty->value,
            'options' => $options,
        ];
    }
}
