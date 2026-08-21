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
        ]);
    }

    public function focusQuestions(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
    ): JsonResponse {
        $this->authorize('update', $classroom);
        abort_unless($liveSession->hasQuestionSet(), 422);

        event(new LiveSessionUpdated($liveSession, ['focus' => 'questions']));

        return ApiResponse::item(['focus' => 'questions']);
    }
}
