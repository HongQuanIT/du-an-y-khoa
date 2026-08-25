<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Events\LiveQuestionChanged;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Services\LiveQuestionPanelService;
use Modules\QuestionBank\Models\QuestionOption;

final class LiveQuestionController extends Controller
{
    public function update(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        LiveQuestionPanelService $panel,
    ): JsonResponse {
        $this->authorize('update', $classroom);

        $validated = $request->validate([
            'index' => ['nullable', 'integer', 'min:0'],
            'option_id' => ['nullable', 'integer'],
        ]);

        $ids = $liveSession->questionIds();
        abort_if($ids === [], 422, 'Session has no question set.');

        $updates = [];
        $targetIndex = (int) $liveSession->current_question_index;
        $before = [
            'current_question_index' => $targetIndex,
            'revealed_option_ids' => $liveSession->revealedOptionIds(),
            'show_answer' => (bool) $liveSession->show_answer,
        ];

        if (array_key_exists('index', $validated)) {
            $targetIndex = min(
                max(0, (int) $validated['index']),
                count($ids) - 1,
            );
            $updates['current_question_index'] = $targetIndex;
        }

        if (array_key_exists('option_id', $validated) && $validated['option_id'] !== null) {
            $optionId = (int) $validated['option_id'];
            $questionId = $ids[$targetIndex] ?? null;
            abort_if($questionId === null, 422, 'Session has no question set.');

            $allowed = QuestionOption::query()
                ->where('question_id', $questionId)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            abort_unless(in_array($optionId, $allowed, true), 422, 'Đáp án không thuộc câu đang chữa.');

            $revealed = $liveSession->revealedOptionIds();
            $revealed = in_array($optionId, $revealed, true)
                ? array_values(array_filter($revealed, static fn (int $id): bool => $id !== $optionId))
                : [...$revealed, $optionId];

            $updates['revealed_option_ids'] = $revealed;
            $updates['show_answer'] = false;
        }

        if ($updates !== []) {
            $liveSession->update($updates);
            $liveSession->refresh();
        }

        $data = $panel->panel($liveSession);
        event(new LiveQuestionChanged(
            $liveSession,
            $data['index'],
            $data['show_answer'],
            $data['question'],
        ));

        if ($updates !== []) {
            Auditor::record(
                AuditAction::ClassroomQuestionChanged,
                $request->user(),
                $liveSession,
                $before,
                [
                    'current_question_index' => (int) $liveSession->current_question_index,
                    'revealed_option_ids' => $liveSession->revealedOptionIds(),
                    'show_answer' => (bool) $liveSession->show_answer,
                ],
                metadata: ['classroom_id' => $classroom->getKey(), 'live_session_id' => $liveSession->getKey()],
                context: new AuditContext(sessionId: (string) $liveSession->getKey()),
            );
        }

        return ApiResponse::item($data);
    }
}
