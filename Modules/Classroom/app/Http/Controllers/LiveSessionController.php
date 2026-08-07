<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Classroom\Actions\EndLiveSessionAction;
use Modules\Classroom\Actions\ScheduleLiveSessionAction;
use Modules\Classroom\Actions\StartLiveSessionAction;
use Modules\Classroom\Http\Requests\ScheduleSessionRequest;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;

final class LiveSessionController extends Controller
{
    public function store(
        ScheduleSessionRequest $request,
        Classroom $classroom,
        ScheduleLiveSessionAction $action,
    ): RedirectResponse {
        $this->authorize('manageLive', $classroom);

        $session = $action->handle($classroom, $request->sessionPayload());

        return redirect()
            ->route('classroom.show', $classroom)
            ->with('success', 'Đã lên lịch buổi live: '.$session->title);
    }

    public function start(
        Classroom $classroom,
        LiveSession $liveSession,
        StartLiveSessionAction $action,
    ): RedirectResponse {
        $this->authorize('manageLive', $classroom);

        $action->handle($classroom, $liveSession);

        return redirect()
            ->route('classroom.live', [$classroom, $liveSession])
            ->with('success', 'Buổi live đã bắt đầu.');
    }

    public function end(
        Classroom $classroom,
        LiveSession $liveSession,
        EndLiveSessionAction $action,
    ): RedirectResponse {
        $this->authorize('manageLive', $classroom);

        $action->handle($classroom, $liveSession);

        return redirect()
            ->route('classroom.live', [$classroom, $liveSession])
            ->with('success', 'Buổi live đã kết thúc.');
    }
}
