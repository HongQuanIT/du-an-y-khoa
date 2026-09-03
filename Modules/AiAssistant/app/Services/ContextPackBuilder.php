<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Models\User;
use Modules\AiAssistant\Enums\TutorPreset;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;

/**
 * Server-side context pack for the tutor. The client never supplies the correct
 * answer / explanation — everything below is resolved from the immutable session
 * snapshot and gated so unanswered (Study) questions never leak the key.
 */
final class ContextPackBuilder
{
    public function __construct(private readonly QuestionSessionSnapshots $snapshots) {}

    /**
     * @return array{
     *     found: bool,
     *     answered: bool,
     *     is_correct: bool|null,
     *     label: string,
     *     pack: array<string, mixed>
     * }
     */
    public function forQuestion(User $user, QuestionSession $session, string $questionId, ?TutorPreset $preset = null): array
    {
        $question = $this->snapshots->question($session, $questionId);

        if (! $question instanceof Question) {
            return ['found' => false, 'answered' => false, 'is_correct' => null, 'label' => 'Câu hỏi', 'pack' => []];
        }

        $attempt = QuestionAttempt::query()
            ->where('session_id', $session->getKey())
            ->where('question_id', $questionId)
            ->first();

        $answered = $attempt instanceof QuestionAttempt;
        $isCorrect = $answered ? (bool) $attempt->is_correct : null;
        $selectedIds = $answered ? array_map('intval', (array) $attempt->selected_option_ids) : [];

        // Spoilers only ever leave the server once the learner has already
        // submitted — and never for the explicit no-spoiler preset.
        $allowSpoiler = $answered && ($preset === null || $preset->allowsSpoiler());

        $topics = $question->medicalTaxonomyNodes->pluck('name')->filter()->values()->all();
        $code = (string) ($question->code ?? '');
        $label = 'Câu'.($code !== '' ? ' '.$code : '').($topics !== [] ? ' — '.$topics[0] : '');

        $options = $question->options->map(function (QuestionOption $option) use ($selectedIds, $allowSpoiler): array {
            $row = [
                'label' => (string) $option->label,
                'content' => strip_tags((string) $option->content),
                'selected' => in_array((int) $option->getKey(), $selectedIds, true),
            ];

            if ($allowSpoiler) {
                $row['is_correct'] = (bool) $option->is_correct;
                if (! empty($option->explanation)) {
                    $row['explanation'] = strip_tags((string) $option->explanation);
                }
            }

            return $row;
        })->values()->all();

        $pack = [
            'question_id' => (string) $question->getKey(),
            'code' => $code,
            'topics' => $topics,
            'stem' => strip_tags((string) $question->stem),
            'options' => $options,
            'answered' => $answered,
        ];

        if ($allowSpoiler) {
            $pack['official_explanation'] = strip_tags((string) ($question->explanation ?? ''));
            $pack['key_info'] = array_values((array) ($question->key_info ?? []));
            if (! empty($question->attending_tip)) {
                $pack['attending_tip'] = strip_tags((string) $question->attending_tip);
            }
            $pack['user_selected_labels'] = $this->labelsFor($question, $selectedIds);
            $pack['correct_labels'] = $question->options
                ->filter(fn (QuestionOption $o): bool => (bool) $o->is_correct)
                ->pluck('label')->values()->all();
            $pack['is_correct_attempt'] = $isCorrect;
        }

        return [
            'found' => true,
            'answered' => $answered,
            'is_correct' => $isCorrect,
            'label' => trim($label) !== '' ? trim($label) : 'Câu hỏi',
            'pack' => $pack,
        ];
    }

    /** @param array<int, int> $selectedIds @return array<int, string> */
    private function labelsFor(Question $question, array $selectedIds): array
    {
        return $question->options
            ->filter(fn (QuestionOption $o): bool => in_array((int) $o->getKey(), $selectedIds, true))
            ->pluck('label')
            ->values()
            ->all();
    }

    /** Which preset the server picks for auto-start (never trusts the client). */
    public function decidePreset(string $source, bool $answered, ?bool $isCorrect, ?string $selection): TutorPreset
    {
        if ($selection !== null && trim($selection) !== '') {
            return TutorPreset::ExplainSelection;
        }

        if (! $answered) {
            return TutorPreset::AnalyzeWithoutSpoiler;
        }

        return $isCorrect ? TutorPreset::ExplainDeeper : TutorPreset::ExplainMistake;
    }
}
