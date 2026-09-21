<?php

declare(strict_types=1);

namespace Modules\Exam\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamTopic;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionFilterBuilder;
use Modules\QuestionBank\Support\ServePublishedQuestion;

/**
 * Pick published bank questions for an exam's CCT quotas and sync the exam_question pivot.
 */
final class GenerateExamQuestionsAction
{
    public function __construct(private readonly QuestionFilterBuilder $filterBuilder) {}

    /**
     * @return array{synced: int, errors: list<string>}
     */
    public function handle(Exam $exam): array
    {
        $exam->load('examTopics.coreClinicalTopic');
        $usedQuestionIds = [];
        $syncData = [];
        $order = 1;
        $errors = [];

        foreach ($exam->examTopics as $examTopic) {
            $topicName = $examTopic->coreClinicalTopic?->name ?? ('#'.$examTopic->core_clinical_topic_id);
            $rawCounts = ExamTopic::normalizeDifficultyCounts($examTopic->difficulty_counts);
            $fromDifficulty = ExamTopic::sumDifficultyCounts($rawCounts);

            if ($fromDifficulty > 0) {
                foreach (Difficulty::cases() as $difficulty) {
                    $needed = $rawCounts[$difficulty->value] ?? 0;
                    if ($needed <= 0) {
                        continue;
                    }

                    $ids = $this->pick(
                        (int) $examTopic->core_clinical_topic_id,
                        $needed,
                        $difficulty,
                        $usedQuestionIds,
                    );

                    if ($ids->count() < $needed) {
                        $available = $this->eligibleCountForDifficulty(
                            (int) $examTopic->core_clinical_topic_id,
                            $difficulty,
                        );
                        $errors[] = sprintf(
                            '%s / %s cần %d câu nhưng chỉ có %d eligible (thiếu %d).',
                            $topicName,
                            $difficulty->label(),
                            $needed,
                            $available,
                            $needed - $ids->count(),
                        );
                    }

                    foreach ($ids as $questionId) {
                        $syncData[(string) $questionId] = [
                            'order' => $order++,
                            'core_clinical_topic_id' => $examTopic->core_clinical_topic_id,
                        ];
                        $usedQuestionIds[] = (string) $questionId;
                    }
                }

                continue;
            }

            $needed = (int) $examTopic->question_count;
            if ($needed <= 0) {
                continue;
            }

            $ids = $this->pick(
                (int) $examTopic->core_clinical_topic_id,
                $needed,
                null,
                $usedQuestionIds,
            );

            if ($ids->count() < $needed) {
                $available = array_sum($this->eligibleCountsByDifficulty((int) $examTopic->core_clinical_topic_id));
                $errors[] = sprintf(
                    '%s cần %d câu nhưng chỉ có %d eligible (thiếu %d).',
                    $topicName,
                    $needed,
                    $available,
                    $needed - $ids->count(),
                );
            }

            foreach ($ids as $questionId) {
                $syncData[(string) $questionId] = [
                    'order' => $order++,
                    'core_clinical_topic_id' => $examTopic->core_clinical_topic_id,
                ];
                $usedQuestionIds[] = (string) $questionId;
            }
        }

        if ($errors === []) {
            $exam->questions()->sync($syncData);
        }

        return [
            'synced' => count($syncData),
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<string>  $usedQuestionIds
     * @return Collection<int, int|string>
     */
    private function pick(
        int $coreClinicalTopicId,
        int $needed,
        ?Difficulty $difficulty,
        array $usedQuestionIds,
    ): Collection {
        $picked = collect();

        $priorityIds = $this->bankQuery($difficulty)
            ->tap(fn (Builder $query) => $this->filterBuilder->whereMatchesCoreClinicalTopicPriorityLessons(
                $query,
                $coreClinicalTopicId,
            ))
            ->whereNotIn('id', $usedQuestionIds)
            ->orderByDesc('created_at')
            ->limit($needed)
            ->pluck('id');

        $picked = $picked->merge($priorityIds)->unique()->values();

        $stillNeeded = $needed - $picked->count();
        if ($stillNeeded > 0) {
            $fallbackIds = $this->bankQuery($difficulty)
                ->tap(fn (Builder $query) => $this->filterBuilder->whereMatchesCoreClinicalTopic(
                    $query,
                    $coreClinicalTopicId,
                ))
                ->whereNotIn('id', [...$usedQuestionIds, ...$picked->all()])
                ->orderByDesc('created_at')
                ->limit($stillNeeded)
                ->pluck('id');

            $picked = $picked->merge($fallbackIds)->unique()->values();
        }

        return $picked;
    }

    /**
     * @return array<string, int>
     */
    public function eligibleCountsByDifficulty(int $coreClinicalTopicId): array
    {
        $counts = ExamTopic::emptyDifficultyCounts();

        $rows = $this->bankQuery()
            ->selectRaw('difficulty, COUNT(*) as aggregate')
            ->tap(fn (Builder $query) => $this->filterBuilder->whereMatchesCoreClinicalTopic($query, $coreClinicalTopicId))
            ->groupBy('difficulty')
            ->pluck('aggregate', 'difficulty');

        foreach ($rows as $difficulty => $aggregate) {
            $key = (string) $difficulty;
            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $aggregate;
            }
        }

        return $counts;
    }

    private function eligibleCountForDifficulty(int $coreClinicalTopicId, Difficulty $difficulty): int
    {
        return $this->bankQuery($difficulty)
            ->tap(fn (Builder $query) => $this->filterBuilder->whereMatchesCoreClinicalTopic($query, $coreClinicalTopicId))
            ->count();
    }

    /** @return Builder<Question> */
    private function bankQuery(?Difficulty $difficulty = null): Builder
    {
        return ServePublishedQuestion::scopeAvailable(Question::query())
            ->when($difficulty !== null, fn (Builder $query) => $query->where('difficulty', $difficulty->value));
    }
}
