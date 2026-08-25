<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
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
        $this->audit($request->user(), AuditAction::ClassroomLiveScheduled, $classroom, $session);

        return redirect()
            ->route('classroom.show', $classroom)
            ->with('success', 'Đã lên lịch buổi live: '.$session->title);
    }

    public function start(
        Classroom $classroom,
        LiveSession $liveSession,
        StartLiveSessionAction $action,
    ): RedirectResponse {
        $this->authorize('startLive', $classroom);

        $session = $action->handle($classroom, $liveSession);
        $this->audit(auth()->user(), AuditAction::ClassroomLiveStarted, $classroom, $session);

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

        $session = $action->handle($classroom, $liveSession);
        $this->audit(auth()->user(), AuditAction::ClassroomLiveEnded, $classroom, $session);

        return redirect()
            ->route('classroom.live', [$classroom, $liveSession])
            ->with('success', 'Buổi live đã kết thúc.');
    }

    private function audit(?User $actor, AuditAction $action, Classroom $classroom, LiveSession $session): void
    {
        Auditor::record(
            $action,
            $actor,
            $session,
            metadata: ['classroom_id' => $classroom->getKey(), 'live_session_id' => $session->getKey()],
            context: new AuditContext(sessionId: (string) $session->getKey()),
        );
    }
}
