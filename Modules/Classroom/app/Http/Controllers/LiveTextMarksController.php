<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Classroom\Events\LiveTextMarksUpdated;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Services\LiveTextMarksService;

final class LiveTextMarksController extends Controller
{
    public function update(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        LiveTextMarksService $marks,
    ): JsonResponse {
        $this->authorize('update', $classroom);

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['add', 'remove', 'clear'])],
            'mark_id' => ['required_if:action,remove', 'nullable', 'string', 'max:40'],
            'question_id' => ['required_if:action,add,clear', 'nullable', 'string', 'max:40'],
            'target' => ['required_if:action,add', 'nullable', 'string', Rule::in(['stem', 'option', 'explanation'])],
            'option_id' => ['nullable', 'integer', 'min:1'],
            'start' => ['required_if:action,add', 'nullable', 'integer', 'min:0'],
            'end' => ['required_if:action,add', 'nullable', 'integer', 'min:1'],
            'color' => ['required_if:action,add', 'nullable', 'string', Rule::in(LiveTextMarksService::COLORS)],
        ]);

        $next = match ($validated['action']) {
            'add' => $marks->add($liveSession, $validated),
            'remove' => $marks->remove($liveSession, (string) $validated['mark_id']),
            'clear' => $marks->clearForQuestion($liveSession, (string) $validated['question_id']),
        };

        event(new LiveTextMarksUpdated(
            $liveSession,
            $next,
            $request->user() !== null ? (int) $request->user()->getAuthIdentifier() : null,
        ));

        return ApiResponse::item(['marks' => $next]);
    }
}
