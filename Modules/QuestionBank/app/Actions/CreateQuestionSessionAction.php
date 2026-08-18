<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Modules\QuestionBank\Services\SessionQuestionSelector;
use RuntimeException;

/**
 * Use case: create a custom / exam / weak-topics practice session.
 */
final class CreateQuestionSessionAction
{
    use AsAction;

    public function __construct(
        private readonly SessionQuestionSelector $selector,
        private readonly QuestionSessionSnapshots $snapshots,
    ) {}

    public function handle(User $user, CreateSessionData $data): QuestionSession
    {
        $count = max(1, $data->count);
        $data = new CreateSessionData(
            mode: $data->mode,
            source: $data->source,
            count: $count,
            topicIds: $data->topicIds,
            difficulties: $data->difficulties,
            questionStatuses: $data->questionStatuses,
            questionStatusMode: $data->questionStatusMode,
            savedOnly: $data->savedOnly,
            folderId: $data->folderId,
            examKey: $data->examKey,
            examId: $data->examId,
            articles: $data->articles,
            symptoms: $data->symptoms,
        );

        $questionIds = $this->selector->forSession($user, $data);

        if ($questionIds === []) {
            throw new RuntimeException('Không còn câu hỏi phù hợp với bộ lọc đã chọn.');
        }

        $actualCount = count($questionIds);
        $data = new CreateSessionData(
            mode: $data->mode,
            source: $data->source,
            count: $actualCount,
            topicIds: $data->topicIds,
            difficulties: $data->difficulties,
            questionStatuses: $data->questionStatuses,
            questionStatusMode: $data->questionStatusMode,
            savedOnly: $data->savedOnly,
            folderId: $data->folderId,
            examKey: $data->examKey,
            examId: $data->examId,
            articles: $data->articles,
            symptoms: $data->symptoms,
        );
        $timeLimit = $data->mode === SessionMode::Exam ? $actualCount * 90 : null;

        return DB::transaction(function () use ($user, $data, $questionIds, $timeLimit): QuestionSession {
            $session = QuestionSession::create([
                'user_id' => $user->getKey(),
                'mode' => $data->mode,
                'status' => SessionStatus::Active,
                'source' => $data->source,
                'filters' => $data->filtersPayload(),
                'question_ids' => $questionIds,
                'total' => count($questionIds),
                'answered_count' => 0,
                'correct_count' => 0,
                'time_limit_seconds' => $timeLimit,
                'paused_state' => null,
                'annotations' => [],
                'exam_id' => $data->examId,
            ]);
            $this->snapshots->capture($session);

            return $session;
        });
    }
}
