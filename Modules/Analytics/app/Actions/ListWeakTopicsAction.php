<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Collection;
use Modules\Analytics\Models\TopicMastery;
use Modules\QuestionBank\Enums\UserQuestionStatus;

/**
 * Use case: the taxonomy nodes a learner is weakest at (srs/modules/20).
 */
final class ListWeakTopicsAction
{
    use AsAction;

    /**
     * @return Collection<int, array{id: int, name: string, accuracy: int, attempts: int, incorrect: int, practice_url: string}>
     */
    public function handle(User $user, int $limit = 3): Collection
    {
        return TopicMastery::query()
            ->select('topic_mastery.*')
            ->selectSub(function ($query) use ($user): void {
                $query->from('question_medical_topics')
                    ->join('question_status', 'question_status.question_id', '=', 'question_medical_topics.question_id')
                    ->selectRaw('COUNT(DISTINCT question_status.question_id)')
                    ->whereColumn(
                        'question_medical_topics.medical_taxonomy_node_id',
                        'topic_mastery.medical_taxonomy_node_id',
                    )
                    ->where('question_status.user_id', $user->getKey())
                    ->where('question_status.status', UserQuestionStatus::Incorrect->value);
            }, 'unresolved_incorrect')
            ->with('medicalTaxonomyNode')
            ->where('user_id', $user->getKey())
            ->where('attempts', '>', 0)
            ->whereColumn('correct', '<', 'attempts')
            ->whereExists(function ($query) use ($user): void {
                $query->from('question_medical_topics')
                    ->join('question_status', 'question_status.question_id', '=', 'question_medical_topics.question_id')
                    ->selectRaw('1')
                    ->whereColumn(
                        'question_medical_topics.medical_taxonomy_node_id',
                        'topic_mastery.medical_taxonomy_node_id',
                    )
                    ->where('question_status.user_id', $user->getKey())
                    ->where('question_status.status', UserQuestionStatus::Incorrect->value);
            })
            ->orderBy('correct_rate')
            ->limit($limit)
            ->get()
            ->map(function (TopicMastery $mastery) {
                $unresolved = (int) $mastery->getAttribute('unresolved_incorrect');
                $incorrectCount = $unresolved > 0 ? $unresolved : max(1, (int) ($mastery->attempts - $mastery->correct));

                return [
                    'id' => (int) $mastery->medical_taxonomy_node_id,
                    'name' => $mastery->medicalTaxonomyNode?->name ?? 'Không rõ',
                    'accuracy' => (int) round($mastery->correct_rate),
                    'attempts' => (int) $mastery->attempts,
                    'incorrect' => $incorrectCount,
                    'practice_url' => route('qbank.create', [
                        'medical_taxonomy_node_ids' => [$mastery->medical_taxonomy_node_id],
                        'question_statuses' => ['incorrect'],
                    ]),
                ];
            });
    }
}
