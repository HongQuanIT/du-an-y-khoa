<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\StudyPlan\Actions\RescheduleTaskAction;
use Modules\StudyPlan\Actions\SkipPlanTaskAction;
use Modules\StudyPlan\Actions\StartPlanTaskAction;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;
use RuntimeException;

/**
 * Task actions from the timeline and calendar: start, skip, move.
 *
 * Completion happens only when the learner finishes the session questions
 * (see AnswerPlanQuestionAction) — there is no manual "mark done" control.
 */
final class StudyPlanTaskController extends Controller
{
    public function __construct(
        private readonly StartPlanTaskAction $startTask,
        private readonly SkipPlanTaskAction $skipTask,
        private readonly RescheduleTaskAction $rescheduleTask,
    ) {}

    public function start(StudyPlan $plan, StudyPlanTask $task): RedirectResponse
    {
        $this->authorize('update', $plan);

        if (! $task->type->isSupported()) {
            return back()->with('status', 'Loại nhiệm vụ này sẽ mở khi module tương ứng sẵn sàng.');
        }

        try {
            $this->startTask->handle($task);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }

        return redirect()->route('study-plan.session', [$plan, $task]);
    }

    public function skip(StudyPlan $plan, StudyPlanTask $task): RedirectResponse
    {
        $this->authorize('update', $plan);

        $this->skipTask->handle($task);

        return back()->with('status', 'Đã bỏ qua nhiệm vụ.');
    }

    public function reschedule(Request $request, StudyPlan $plan, StudyPlanTask $task): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$plan->exam_target_date->toDateString()],
        ], [
            'date.before_or_equal' => 'Ngày mới phải trước ngày thi.',
        ]);

        $this->rescheduleTask->handle($task, Carbon::parse($validated['date']));

        return back()->with('status', 'Đã dời nhiệm vụ sang ngày mới.');
    }
}
