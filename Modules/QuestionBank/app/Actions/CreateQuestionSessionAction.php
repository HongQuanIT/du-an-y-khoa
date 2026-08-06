<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Entitlement;
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
        $maxQuestions = $user->hasEntitlement(Entitlement::QbankFull->value) ? 100 : 20;
        $count = max(1, min($maxQuestions, $data->count));
        $data = new CreateSessionData(
            mode: $data->mode,
            source: $data->source,
            count: $count,
            topicIds: $data->topicIds,
            difficulties: $data->difficulties,
            questionStatuses: $data->questionStatuses,
            questionStatusMode: $data->questionStatusMode,
            savedOnly: $data->savedOnly,
            timeLimitSeconds: $data->timeLimitSeconds,
            examKey: $data->examKey,
            articles: $data->articles,
            symptoms: $data->symptoms,
        );

        $questionIds = $this->selector->forSession($user, $data);

        if ($questionIds === []) {
            throw new RuntimeException('Không còn câu hỏi phù hợp với bộ lọc đã chọn.');
        }

        $timeLimit = $data->timeLimitSeconds;
        if ($data->mode === SessionMode::Exam && ($timeLimit === null || $timeLimit <= 0)) {
            // ~90s / câu cho mock exam mặc định.
            $timeLimit = count($questionIds) * 90;
        }

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
            ]);
            $this->snapshots->capture($session);

            return $session;
        });
    }
}
