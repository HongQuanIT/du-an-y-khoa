<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use App\Support\Html\SafeHtml;
use Illuminate\Support\Str;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\Topic;

/**
 * Builds the immutable summary/review read model for a session.
 */
final class QuestionSessionInsights
{
    public function __construct(private readonly QuestionSessionSnapshots $snapshots) {}

    /**
     * @return array{
     *   total: int, answered: int, correct: int, wrong: int, skipped: int,
     *   flagged: int, accuracy: int, time_spent_seconds: int,
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
        $wrong = 0;
        $skipped = 0;
        $flagged = 0;
        $timeSpent = 0;
        /** @var array<string, array{name: string, correct: int, wrong: int, skipped: int, total: int}> $byTopic */
        $byTopic = [];

        foreach ($questionIds as $questionId) {
            $question = $questions[(string) $questionId] ?? null;
            $attempt = $attempts[(string) $questionId] ?? null;
            $topic = $question instanceof Question ? $question->getRelation('topic') : null;
            $topicNames = $question instanceof Question
                ? $question->topics->pluck('name')->map(fn ($name): string => (string) $name)->all()
                : [];
            if ($topicNames === []) {
                $topicNames = [$topic instanceof Topic ? (string) $topic->name : 'Tổng hợp'];
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

            $annotation = ($session->annotations ?? [])[(string) $questionId] ?? [];
            if ((bool) ($annotation['flagged']
                ?? ($attempt instanceof QuestionAttempt && $attempt->flagged))) {
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
        $correctShare = $total > 0 ? $correct / $total * 100 : 0;
        $wrongShare = $total > 0 ? $wrong / $total * 100 : 0;
        $donutStyle = sprintf(
            'conic-gradient(#16A34A 0%% %.2f%%, #DC2626 %.2f%% %.2f%%, #BDC9C6 %.2f%% 100%%)',
            $correctShare,
            $correctShare,
            $correctShare + $wrongShare,
            $correctShare + $wrongShare,
        );

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
            'wrong' => $wrong,
            'skipped' => $skipped,
            'flagged' => $flagged,
            'accuracy' => $accuracy,
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
            $topic = $question->getRelation('topic');

            $items[] = [
                'id' => 'Q'.($position + 1),
                'question_id' => (string) $questionId,
                'index' => $position,
                'result' => $result,
                'topic' => $question->topics->pluck('name')->join(', ')
                    ?: ($topic instanceof Topic ? (string) $topic->name : 'Tổng hợp'),
                'excerpt' => Str::limit(strip_tags((string) $question->stem), 140),
                'stem' => (string) $question->stem,
                'stem_html' => (string) ($annotation['stem_html'] ?? SafeHtml::forDisplay((string) $question->stem)),
                'note' => (string) ($annotation['note'] ?? ''),
                'note_html' => (string) ($annotation['note_html'] ?? nl2br(e((string) ($annotation['note'] ?? '')))),
                'flagged' => (bool) ($annotation['flagged']
                    ?? ($attempt instanceof QuestionAttempt && $attempt->flagged)),
                'explanation' => (string) ($question->explanation ?? ''),
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
}
