<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Models\Question;

final class SyncQuestionStatsAction
{
    use AsAction;

    /**
     * @param  list<string>  $questionIds
     */
    public function syncForQuestionIds(array $questionIds): int
    {
        $questionIds = array_values(array_unique(array_filter($questionIds)));

        if ($questionIds === []) {
            return 0;
        }

        $attemptStats = $this->attemptStatsFor($questionIds);
        $feedbackStats = $this->feedbackStatsFor($questionIds);
        $now = now();
        $updated = 0;

        foreach ($questionIds as $questionId) {
            $attemptRow = $attemptStats->get($questionId);
            $totalAttempts = (int) ($attemptRow->total_attempts ?? 0);
            $correctAttempts = (int) ($attemptRow->correct_attempts ?? 0);
            $incorrectAttempts = (int) ($attemptRow->incorrect_attempts ?? 0);
            $reportsByReason = $feedbackStats->get($questionId, collect())
                ->mapWithKeys(fn ($row): array => [(string) $row->category => (int) $row->total])
                ->all();

            Question::query()
                ->whereKey($questionId)
                ->update([
                    'stats_cache' => [
                        'total_attempts' => $totalAttempts,
                        'study_mode_attempts' => (int) ($attemptRow->study_mode_attempts ?? 0),
                        'exam_mode_attempts' => (int) ($attemptRow->exam_mode_attempts ?? 0),
                        'correct_attempts' => $correctAttempts,
                        'incorrect_attempts' => $incorrectAttempts,
                        'correct_rate' => $totalAttempts > 0 ? round($correctAttempts / $totalAttempts, 4) : null,
                        'average_score' => null,
                        'total_reports' => array_sum($reportsByReason),
                        'reports_by_reason' => $reportsByReason,
                    ],
                    'stats_updated_at' => $now,
                ]);

            $updated++;
        }

        return $updated;
    }

    public function syncForQuestion(Question|string $question): void
    {
        $questionId = $question instanceof Question ? (string) $question->getKey() : $question;
        $this->syncForQuestionIds([$questionId]);
    }

    /**
     * @param  list<string>  $questionIds
     */
    public function syncStaleOrMissing(int $limit = 500): int
    {
        $questionIds = Question::query()
            ->whereIn('id', function ($query): void {
                $query->select('question_id')
                    ->from('question_attempts')
                    ->distinct();
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('stats_cache')
                    ->orWhereNull('stats_updated_at');
            })
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        return $this->syncForQuestionIds($questionIds);
    }

    /**
     * @param  list<string>  $questionIds
     * @return Collection<string, object{
     *     question_id: string,
     *     total_attempts: int,
     *     correct_attempts: int,
     *     incorrect_attempts: int,
     *     study_mode_attempts: int,
     *     exam_mode_attempts: int
     * }>
     */
    private function attemptStatsFor(array $questionIds): Collection
    {
        return DB::table('question_attempts')
            ->join('question_sessions', 'question_sessions.id', '=', 'question_attempts.session_id')
            ->whereIn('question_attempts.question_id', $questionIds)
            ->whereNotNull('question_attempts.is_correct')
            ->groupBy('question_attempts.question_id')
            ->select([
                'question_attempts.question_id',
            ])
            ->selectRaw('COUNT(*) as total_attempts')
            ->selectRaw('SUM(CASE WHEN question_attempts.is_correct = 1 THEN 1 ELSE 0 END) as correct_attempts')
            ->selectRaw('SUM(CASE WHEN question_attempts.is_correct = 0 THEN 1 ELSE 0 END) as incorrect_attempts')
            ->selectRaw("SUM(CASE WHEN question_sessions.mode = 'study' THEN 1 ELSE 0 END) as study_mode_attempts")
            ->selectRaw("SUM(CASE WHEN question_sessions.mode = 'exam' THEN 1 ELSE 0 END) as exam_mode_attempts")
            ->get()
            ->keyBy('question_id');
    }

    /**
     * @param  list<string>  $questionIds
     * @return Collection<string, Collection<int, object{category: string, total: int}>>
     */
    private function feedbackStatsFor(array $questionIds): Collection
    {
        return DB::table('question_feedback')
            ->whereIn('question_id', $questionIds)
            ->groupBy('question_id', 'category')
            ->select([
                'question_id',
                'category',
            ])
            ->selectRaw('COUNT(*) as total')
            ->get()
            ->groupBy('question_id');
    }
}
