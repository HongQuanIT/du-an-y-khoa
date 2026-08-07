<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Events\LiveQuestionChanged;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Services\LiveQuestionPanelService;

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
            'show_answer' => ['nullable', 'boolean'],
        ]);

        $ids = $liveSession->questionIds();
        abort_if($ids === [], 422, 'Session has no question set.');

        $updates = [];

        if (array_key_exists('index', $validated)) {
            $updates['current_question_index'] = min(
                max(0, (int) $validated['index']),
                count($ids) - 1,
            );
        }

        if (array_key_exists('show_answer', $validated)) {
            $updates['show_answer'] = (bool) $validated['show_answer'];
        }

        if ($updates !== []) {
            $liveSession->update($updates);
            $liveSession->refresh();
        }

        $data = $panel->panel($liveSession, $request->user());
        event(new LiveQuestionChanged(
            $liveSession,
            $data['index'],
            $data['show_answer'],
            $data['question'],
        ));

        return ApiResponse::item($data);
    }
}
