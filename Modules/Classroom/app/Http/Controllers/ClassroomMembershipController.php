<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Actions\JoinClassroomAction;
use Modules\Classroom\Actions\LeaveClassroomAction;
use Modules\Classroom\Models\Classroom;

final class ClassroomMembershipController extends Controller
{
    public function join(Request $request, Classroom $classroom, JoinClassroomAction $action): RedirectResponse
    {
        $this->authorize('join', $classroom);

        $action->handle(
            $request->user(),
            $classroom,
            $request->string('join_code')->toString() ?: null,
        );

        return redirect()
            ->route('classroom.show', $classroom)
            ->with('success', 'Bạn đã tham gia lớp.');
    }

    public function leave(Request $request, Classroom $classroom, LeaveClassroomAction $action): RedirectResponse
    {
        $action->handle($request->user(), $classroom);

        return redirect()
            ->route('classroom.index')
            ->with('success', 'Bạn đã rời lớp.');
    }
}
