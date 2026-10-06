<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use App\Support\Html\SafeHtml;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Modules\Personalization\Models\Note;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Support\ServePublishedQuestion;

/**
 * Builds the immutable summary/review read model for a session.
 */
final class QuestionSessionInsights
{
    public function __construct(private readonly QuestionSessionSnapshots $snapshots) {}

    /**
     * @return array{
     *   total: int, answered: int, correct: int, correct_with_hint: int, wrong: int, skipped: int,
     *   flagged: int, accuracy: int, hint_accuracy: int, time_spent_seconds: int,
     *   donut_style: string, topics: list<array<string, mixed>>
     * }
     */
    public function summary(QuestionSession $session): array
    {
        $questionIds = $session->question_ids ?? [];
        $attempts = $this->attempts($session);
        $questions = $this->snapshots->questionMap($session);
        $questionIds = array_values(array_filter(
            $questionIds,
            fn (string $questionId): bool => isset($questions[$questionId]),
        ));

        $correct = 0;
        $correctWithHint = 0;
        $wrong = 0;
        $skipped = 0;
        $flagged = 0;
        $timeSpent = 0;
        /** @var array<string, array{name: string, correct: int, wrong: int, skipped: int, total: int}> $byTopic */
        $byTopic = [];
        $flaggedQuestionIdSet = array_fill_keys(
            UserQuestionStatusModel::query()
                ->where('user_id', $session->user_id)
                ->where('flagged', true)
                ->whereIn('question_id', $questionIds)
                ->pluck('question_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all(),
            true,
        );

        foreach ($questionIds as $questionId) {
            $question = $questions[(string) $questionId] ?? null;
            $attempt = $attempts[(string) $questionId] ?? null;
            $topicNames = $question instanceof Question
                ? $question->lessons->pluck('name')->map(fn ($name): string => (string) $name)->all()
                : [];
            if ($topicNames === []) {
                $topicNames = ['Tổng hợp'];
            }
            foreach ($topicNames as $topicName) {
                $byTopic[$topicName] ??= [
                    'name' => $topicName,
                    'correct' => 0,
                    'wrong' => 0,
                    'skipped' => 0,
                    'total' => 0,
                ];
                $byTopic[$topicName]['total']++;
            }

            if (isset($flaggedQuestionIdSet[(string) $questionId])) {
                $flagged++;
            }

            if ($attempt === null || $attempt->is_correct === null) {
                $skipped++;
                foreach ($topicNames as $topicName) {
                    $byTopic[$topicName]['skipped']++;
                }

                continue;
            }

            $timeSpent += (int) $attempt->time_spent_seconds;

            if ($attempt->is_correct) {
                $correct++;
                if ($attempt->used_hint) {
                    $correctWithHint++;
                }
                foreach ($topicNames as $topicName) {
                    $byTopic[$topicName]['correct']++;
                }
            } else {
                $wrong++;
                foreach ($topicNames as $topicName) {
                    $byTopic[$topicName]['wrong']++;
                }
            }
        }

        $total = count($questionIds);
        $answered = $correct + $wrong;
        $accuracy = $total > 0 ? (int) round($correct / $total * 100) : 0;
        $hintAccuracy = $total > 0 ? (int) round($correctWithHint / $total * 100) : 0;
        $donutStyle = self::resultDonutStyle($total, $correct, $correctWithHint, $wrong);

        $topics = collect($byTopic)
            ->map(function (array $row): array {
                $rate = $row['total'] > 0
                    ? (int) round($row['correct'] / $row['total'] * 100)
                    : 0;

                return array_merge($row, [
                    'rate' => $rate,
                    'count' => $row['correct'].'/'.$row['total'],
                ]);
            })
            ->sortBy('rate')
            ->values()
            ->all();

        return [
            'total' => $total,
            'answered' => $answered,
            'correct' => $correct,
            'correct_with_hint' => $correctWithHint,
            'wrong' => $wrong,
            'skipped' => $skipped,
            'flagged' => $flagged,
            'accuracy' => $accuracy,
            'hint_accuracy' => $hintAccuracy,
            'time_spent_seconds' => $timeSpent,
            'donut_style' => $donutStyle,
            'topics' => $topics,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function reviewItems(QuestionSession $session): array
    {
        $questionIds = $session->question_ids ?? [];
        $attempts = $this->attempts($session);
        $questions = $this->snapshots->questionMap($session);
        $keyInfo = app(QuestionKeyInfoRenderer::class);
        $noteMap = Note::questionPayloadMap(
            (int) $session->user_id,
            array_map('strval', $questionIds),
        );
        $flaggedQuestionIdSet = array_fill_keys(
            UserQuestionStatusModel::query()
                ->where('user_id', $session->user_id)
                ->where('flagged', true)
                ->whereIn('question_id', $questionIds)
                ->pluck('question_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all(),
            true,
        );
        $items = [];
        foreach ($questionIds as $position => $questionId) {
            $question = $questions[(string) $questionId] ?? null;
            if (! $question instanceof Question) {
                continue;
            }

            $attempt = $attempts[(string) $questionId] ?? null;
            $options = $question->getRelation('options');
            $selectedIds = $attempt instanceof QuestionAttempt
                ? array_map('intval', $attempt->selected_option_ids ?? [])
                : [];
            $result = match (true) {
                $attempt === null || $attempt->is_correct === null => 'skipped',
                (bool) $attempt->is_correct => 'correct',
                default => 'wrong',
            };
            $annotation = ($session->annotations ?? [])[(string) $questionId] ?? [];
            $hintUsed = (bool) ($annotation['key_info_used'] ?? false);
            $knowledgeUsed = (bool) ($annotation['attending_tip_used'] ?? false);
            if (! $hintUsed && ! $knowledgeUsed && $attempt instanceof QuestionAttempt && $attempt->used_hint) {
                $hintUsed = true;
            }
            $stem = (string) $question->stem;
            $hints = $keyInfo->resolvePhrases($stem, (array) ($question->key_info ?? []));
            $hasKeyInfo = $hints !== [] || str_contains($stem, 'data-hint');
            $knowledgeHtml = SafeHtml::forDisplay((string) ($question->attending_tip ?? ''));
            $notePayload = $noteMap[(string) $questionId] ?? ['note' => '', 'note_html' => ''];

            $items[] = [
                'id' => 'Q'.($position + 1),
                'question_id' => (string) $questionId,
                'index' => $position,
                'result' => $result,
                'topic' => $question->lessons->pluck('name')->join(', ') ?: 'Tổng hợp',
                'excerpt' => Str::limit(strip_tags($stem), 140),
                'stem' => $stem,
                'stem_html' => (string) ($annotation['stem_html'] ?? SafeHtml::forDisplay($stem)),
                'stem_key_info_html' => $keyInfo->render($stem, $hints),
                'hint_used' => $hintUsed,
                'has_key_info' => $hasKeyInfo,
                'knowledge_used' => $knowledgeUsed,
                'knowledge_html' => $knowledgeHtml,
                'note' => $notePayload['note'],
                'note_html' => $notePayload['note_html'] !== ''
                    ? $notePayload['note_html']
                    : ($notePayload['note'] !== '' ? nl2br(e($notePayload['note'])) : ''),
                'flagged' => isset($flaggedQuestionIdSet[(string) $questionId]),
                'options' => $options->map(function ($option) use ($selectedIds): array {
                    $selected = in_array((int) $option->id, $selectedIds, true);
                    $correct = (bool) $option->is_correct;

                    return [
                        'id' => (int) $option->id,
                        'key' => (string) $option->label,
                        'text' => (string) $option->content,
                        'explanation' => (string) ($option->explanation ?? ''),
                        'selected' => $selected,
                        'correct' => $correct,
                        'state' => match (true) {
                            $correct && $selected => 'correct_selected',
                            $correct => 'correct',
                            $selected => 'wrong_selected',
                            default => 'dimmed',
                        },
                    ];
                })->values()->all(),
            ];
        }

        return $items;
    }

    /**
     * Build one row per question for the session overview. Community accuracy
     * uses the latest graded attempt from each user so repeats are not weighted.
     *
     * @return list<array{
     *   id: string, question_id: string, excerpt: string, result: string,
     *   time_spent_seconds: int, peer_accuracy: int|null, peer_users: int,
     *   difficulty: string
     * }>
     */
    public function questionOverview(QuestionSession $session): array
    {
        $questionIds = array_values(array_map('strval', $session->question_ids ?? []));

        if ($questionIds === []) {
            return [];
        }

        $questions = $this->snapshots->questionMap($session);
        $sessionAttempts = $this->attempts($session);
        $latestByQuestionAndUser = [];

        $communityAttempts = QuestionAttempt::query()
            ->whereIn('question_id', $questionIds)
            ->whereNotNull('is_correct')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'question_id', 'is_correct']);

        foreach ($communityAttempts as $attempt) {
            $questionId = (string) $attempt->question_id;
            $userId = (int) $attempt->user_id;

            if (isset($latestByQuestionAndUser[$questionId][$userId])) {
                continue;
            }

            $latestByQuestionAndUser[$questionId][$userId] = (bool) $attempt->is_correct;
        }

        $rows = [];

        foreach ($questionIds as $position => $questionId) {
            $question = $questions[$questionId] ?? null;

            if (! $question instanceof Question) {
                continue;
            }

            $attempt = $sessionAttempts[$questionId] ?? null;
            $peerResults = $latestByQuestionAndUser[$questionId] ?? [];
            $peerUsers = count($peerResults);
            $peerCorrect = count(array_filter($peerResults));
            $difficulty = $question->difficulty;

            $rows[] = [
                'id' => 'Q'.($position + 1),
                'question_id' => $questionId,
                'excerpt' => Str::limit(strip_tags((string) $question->stem), 90),
                'result' => match (true) {
                    ! $attempt instanceof QuestionAttempt || $attempt->is_correct === null => 'skipped',
                    (bool) $attempt->is_correct => 'correct',
                    default => 'wrong',
                },
                'time_spent_seconds' => $attempt instanceof QuestionAttempt
                    ? (int) $attempt->time_spent_seconds
                    : 0,
                'peer_accuracy' => $peerUsers > 0
                    ? (int) round($peerCorrect / $peerUsers * 100)
                    : null,
                'peer_users' => $peerUsers,
                'difficulty' => $difficulty->label(),
            ];
        }

        return $rows;
    }

    /** @return array<string, QuestionAttempt> */
    public function attempts(QuestionSession $session): array
    {
        $attemptModels = QuestionAttempt::query()
            ->where('session_id', $session->getKey())
            ->get();
        $attempts = [];

        foreach ($attemptModels as $attempt) {
            $attempts[(string) $attempt->question_id] = $attempt;
        }

        return $attempts;
    }

    /**
     * Correct-with-hint is a band inside the correct arc, not a separate slice.
     */
    public static function resultDonutStyle(int $total, int $correct, int $correctWithHint, int $wrong): string
    {
        $correctWithHint = min(max(0, $correctWithHint), max(0, $correct));
        $unaidedShare = $total > 0 ? ($correct - $correctWithHint) / $total * 100 : 0;
        $correctShare = $total > 0 ? $correct / $total * 100 : 0;
        $wrongEnd = $correctShare + ($total > 0 ? $wrong / $total * 100 : 0);

        return sprintf(
            'conic-gradient(#16A34A 0%% %.2f%%, #FDE68A %.2f%% %.2f%%, #DC2626 %.2f%% %.2f%%, #BDC9C6 %.2f%% 100%%)',
            $unaidedShare,
            $unaidedShare,
            $correctShare,
            $correctShare,
            $wrongEnd,
            $wrongEnd,
        );
    }

    /**
     * Integer percents that add up to 100. Spare points go to the largest
     * fractional remainders so equal splits such as 2/2/2 do not stop at 99.
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    public static function percentShares(array $counts): array
    {
        $shares = array_fill_keys(array_keys($counts), 0);
        $total = array_sum($counts);
        if ($total <= 0) {
            return $shares;
        }

        $remainders = [];
        foreach ($counts as $key => $count) {
            $exact = max(0, $count) / $total * 100;
            $shares[$key] = (int) floor($exact);
            $remainders[$key] = $exact - $shares[$key];
        }

        $left = 100 - array_sum($shares);
        $order = array_keys($counts);
        usort($order, function (string $leftKey, string $rightKey) use ($remainders, $counts, $order): int {
            $remainder = $remainders[$rightKey] <=> $remainders[$leftKey];
            if ($remainder !== 0) {
                return $remainder;
            }

            $count = $counts[$rightKey] <=> $counts[$leftKey];
            if ($count !== 0) {
                return $count;
            }

            return array_search($leftKey, $order, true) <=> array_search($rightKey, $order, true);
        });

        foreach ($order as $key) {
            if ($left <= 0) {
                break;
            }
            if ($counts[$key] <= 0) {
                continue;
            }
            $shares[$key]++;
            $left--;
        }

        return $shares;
    }

    /**
     * Cumulative lesson progress for the lessons in this session.
     * Counts use the latest graded outcome. Correct-with-hint is the subset
     * whose latest correct attempt used a hint. Not-done is every other question.
     *
     * @return list<array{
     *   lesson_id: int, name: string, total: int, graded: int, coverage: int,
     *   sessions: int, correct: int, correct_with_hint: int, wrong: int, not_done: int,
     *   unaided_share: int, hint_share: int, wrong_share: int, needs_review: bool
     * }>
     */
    public function lessonProgress(QuestionSession $session): array
    {
        $questionIds = array_values(array_unique(array_map(
            'strval',
            $session->question_ids ?? [],
        )));
        if ($questionIds === []) {
            return [];
        }

        $lessons = DB::table('question_lesson')
            ->join('lessons', 'lessons.id', '=', 'question_lesson.lesson_id')
            ->whereIn('question_lesson.question_id', $questionIds)
            ->select('lessons.id', 'lessons.name')
            ->distinct()
            ->orderBy('lessons.name')
            ->get();
        if ($lessons->isEmpty()) {
            return [];
        }

        $lessonIds = $lessons->map(fn ($lesson): int => (int) $lesson->id)->all();
        $links = DB::table('question_lesson')
            ->whereIn('lesson_id', $lessonIds)
            ->get(['lesson_id', 'question_id']);
        $linkedIds = $links->pluck('question_id')->map(fn ($id): string => (string) $id)->unique()->all();
        $available = ServePublishedQuestion::scopeAvailable(Question::query())
            ->whereIn('id', $linkedIds)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
        $availableLookup = array_fill_keys($available, true);

        $statuses = $available === []
            ? collect()
            : UserQuestionStatusModel::query()
                ->where('user_id', $session->user_id)
                ->whereIn('question_id', $available)
                ->get()
                ->keyBy(fn (UserQuestionStatusModel $status): string => (string) $status->question_id);

        $sessionCounts = DB::table('question_attempts')
            ->join('question_lesson', 'question_lesson.question_id', '=', 'question_attempts.question_id')
            ->where('question_attempts.user_id', $session->user_id)
            ->whereIn('question_lesson.lesson_id', $lessonIds)
            ->selectRaw('question_lesson.lesson_id as lesson_id, COUNT(DISTINCT question_attempts.session_id) as sessions')
            ->groupBy('question_lesson.lesson_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->lesson_id => (int) $row->sessions]);

        $hintOnLatestCorrect = $this->latestCorrectUsedHint((int) $session->user_id, $available);

        $questionsByLesson = [];
        foreach ($links as $link) {
            $questionId = (string) $link->question_id;
            if (! isset($availableLookup[$questionId])) {
                continue;
            }
            $questionsByLesson[(int) $link->lesson_id][] = $questionId;
        }

        $rows = [];
        foreach ($lessons as $lesson) {
            $lessonId = (int) $lesson->id;
            $questionIdsForLesson = array_values(array_unique($questionsByLesson[$lessonId] ?? []));
            $correct = 0;
            $correctWithHint = 0;
            $wrong = 0;

            foreach ($questionIdsForLesson as $questionId) {
                $status = $statuses->get($questionId);
                if (! $status instanceof UserQuestionStatusModel) {
                    continue;
                }
                $outcome = $this->latestOutcome($status);
                if ($outcome === 'correct') {
                    $correct++;
                    if ($hintOnLatestCorrect[$questionId] ?? false) {
                        $correctWithHint++;
                    }
                } elseif ($outcome === 'incorrect') {
                    $wrong++;
                }
            }

            $total = count($questionIdsForLesson);
            $graded = $correct + $wrong;
            $hintCount = min($correctWithHint, $correct);
            $notDone = max(0, $total - $graded);
            $shares = self::percentShares([
                'unaided' => $correct - $hintCount,
                'hint' => $hintCount,
                'wrong' => $wrong,
                'not_done' => $notDone,
            ]);
            $rows[] = [
                'lesson_id' => $lessonId,
                'name' => (string) $lesson->name,
                'total' => $total,
                'graded' => $graded,
                'coverage' => $total > 0 ? (int) round($graded / $total * 100) : 0,
                'sessions' => (int) ($sessionCounts[$lessonId] ?? 0),
                'correct' => $correct,
                'correct_with_hint' => $correctWithHint,
                'wrong' => $wrong,
                'not_done' => $notDone,
                'unaided_share' => $shares['unaided'],
                'hint_share' => $shares['hint'],
                'wrong_share' => $shares['wrong'],
                'needs_review' => $graded > 0 && ($correct / $graded) < 0.5,
            ];
        }

        usort($rows, function (array $left, array $right): int {
            $leftRank = ($left['correct'] + $left['wrong']) === 0 ? 1 : 0;
            $rightRank = ($right['correct'] + $right['wrong']) === 0 ? 1 : 0;
            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }

            $leftRate = ($left['correct'] + $left['wrong']) > 0
                ? $left['correct'] / ($left['correct'] + $left['wrong'])
                : 1;
            $rightRate = ($right['correct'] + $right['wrong']) > 0
                ? $right['correct'] / ($right['correct'] + $right['wrong'])
                : 1;

            return $leftRate <=> $rightRate ?: strcmp($left['name'], $right['name']);
        });

        return $rows;
    }

    /**
     * @param  list<string>  $questionIds
     * @return array<string, bool>
     */
    private function latestCorrectUsedHint(int $userId, array $questionIds): array
    {
        if ($questionIds === []) {
            return [];
        }

        $usedHint = [];
        $attempts = QuestionAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('question_id', $questionIds)
            ->whereNotNull('is_correct')
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->get(['question_id', 'is_correct', 'used_hint']);

        foreach ($attempts as $attempt) {
            $questionId = (string) $attempt->question_id;
            if (array_key_exists($questionId, $usedHint)) {
                continue;
            }
            $usedHint[$questionId] = $attempt->is_correct === true && $attempt->used_hint;
        }

        return $usedHint;
    }

    private function latestOutcome(UserQuestionStatusModel $status): ?string
    {
        return match ($status->status) {
            UserQuestionStatus::Correct => 'correct',
            UserQuestionStatus::Incorrect => 'incorrect',
            UserQuestionStatus::Omitted => 'omitted',
            UserQuestionStatus::Marked => $this->markedOutcome($status),
            default => null,
        };
    }

    private function markedOutcome(UserQuestionStatusModel $status): ?string
    {
        if ($status->last_graded_at === null) {
            return null;
        }

        if ($status->last_correct_at !== null && $status->last_correct_at->equalTo($status->last_graded_at)) {
            return 'correct';
        }

        return 'incorrect';
    }
}
