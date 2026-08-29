<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Events\LiveSessionUpdated;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;

final class LivePresenterController extends Controller
{
    public function show(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
    ): View {
        $this->authorize('view', $classroom);
        abort_unless($classroom->isActiveMember($request->user()), 403);
        abort_unless($liveSession->hasQuestionSet(), 404);

        $role = $classroom->roleFor($request->user()) ?? MemberRole::Member;
        $teachPortal = $request->routeIs('teach.*');

        return view('classroom::live.presenter', [
            'classroom' => $classroom,
            'session' => $liveSession,
            'canModerate' => $role->canModerate(),
            'bootstrapUrl' => route($teachPortal ? 'teach.classes.sessions.studio.api.bootstrap' : 'classroom.live.api.bootstrap', [$classroom, $liveSession]),
            'questionUrl' => route($teachPortal ? 'teach.classes.sessions.studio.api.question' : 'classroom.live.api.question', [$classroom, $liveSession]),
            'marksUrl' => route($teachPortal ? 'teach.classes.sessions.studio.api.marks' : 'classroom.live.api.marks', [$classroom, $liveSession]),
            'sessionUuid' => $liveSession->uuid,
        ]);
    }

    public function focusQuestions(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
    ): JsonResponse {
        $this->authorize('update', $classroom);
        abort_unless($liveSession->hasQuestionSet(), 422);

        $liveSession->update(['stage_teach' => true]);

        event(new LiveSessionUpdated($liveSession, [
            'focus' => 'questions',
            'stage_teach' => true,
        ]));

        return ApiResponse::item([
            'focus' => 'questions',
            'stage_teach' => true,
        ]);
    }

    public function updateStage(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
    ): JsonResponse {
        $this->authorize('update', $classroom);
        abort_unless($liveSession->hasQuestionSet(), 422);

        $validated = $request->validate([
            'stage_teach' => ['required', 'boolean'],
        ]);

        $stageTeach = (bool) $validated['stage_teach'];
        $liveSession->update(['stage_teach' => $stageTeach]);

        event(new LiveSessionUpdated($liveSession, [
            'stage_teach' => $stageTeach,
            'focus' => $stageTeach ? 'questions' : null,
        ]));

        return ApiResponse::item(['stage_teach' => $stageTeach]);
    }
}
