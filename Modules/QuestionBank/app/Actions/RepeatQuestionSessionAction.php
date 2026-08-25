<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use RuntimeException;

/** Creates a fresh session from selected result groups while preserving its mode. */
final class RepeatQuestionSessionAction
{
    use AsAction;

    public function __construct(private readonly QuestionSessionSnapshots $snapshots) {}

    /** @param array<int, string> $statuses */
    public function handle(
        User $user,
        QuestionSession $original,
        array $statuses,
        int $count,
        ?array $allowedQuestionIds = null,
    ): QuestionSession {
        $selectedIds = $original->questionIdsForRepeat($statuses);
        if ($allowedQuestionIds !== null) {
            $allowedLookup = array_fill_keys(array_map('strval', $allowedQuestionIds), true);
            $selectedIds = array_values(array_filter(
                $selectedIds,
                static fn (string $id): bool => isset($allowedLookup[$id]),
            ));
        }
        $existingIds = Question::query()
            ->whereIn('id', $selectedIds)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        $snapshotIds = $original->snapshots()
            ->whereIn('question_id', $selectedIds)
            ->pluck('question_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        $existingLookup = array_fill_keys([...$existingIds, ...$snapshotIds], true);
        $questionIds = collect($selectedIds)
            ->filter(static fn (string $id): bool => isset($existingLookup[$id]))
            ->take(max(1, $count))
            ->values()
            ->all();

        if ($questionIds === []) {
            throw new RuntimeException('Không có câu hỏi phù hợp với trạng thái đã chọn.');
        }

        $mode = $original->mode;
        $timeLimitSeconds = $mode === SessionMode::Exam
            ? count($questionIds) * 120
            : null;

        return DB::transaction(function () use (
            $user,
            $original,
            $mode,
            $statuses,
            $questionIds,
            $timeLimitSeconds,
        ): QuestionSession {
            $session = QuestionSession::create([
                'user_id' => $user->getKey(),
                'mode' => $mode,
                'status' => SessionStatus::Active,
                'source' => SessionSource::Custom,
                'filters' => [
                    'name' => 'Làm lại: '.$original->displayName(),
                    'repeated_from_session_id' => (string) $original->getKey(),
                    'repeat_statuses' => array_values($statuses),
                ],
                'question_ids' => $questionIds,
                'total' => count($questionIds),
                'answered_count' => 0,
                'correct_count' => 0,
                'time_limit_seconds' => $timeLimitSeconds,
                'paused_state' => null,
                'annotations' => [],
            ]);
            $this->snapshots->copy($original, $session, $questionIds);

            return $session;
        });
    }
}
