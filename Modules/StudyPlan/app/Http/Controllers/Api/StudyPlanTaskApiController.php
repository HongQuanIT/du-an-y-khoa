<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\StudyPlan\Actions\ListTodayTasksAction;
use Modules\StudyPlan\Actions\RescheduleTaskAction;
use Modules\StudyPlan\Actions\SkipPlanTaskAction;
use Modules\StudyPlan\Actions\StartPlanTaskAction;
use Modules\StudyPlan\Http\Resources\StudyPlanTaskResource;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;
use RuntimeException;

/**
 * REST surface for plan tasks (srs/modules/04 §7).
 *
 * Completion is not exposed: a task finishes only when the session reaches
 * its question target via AnswerPlanQuestionAction.
 */
final class StudyPlanTaskApiController extends Controller
{
    public function __construct(
        private readonly ListTodayTasksAction $todayTasks,
        private readonly StartPlanTaskAction $startTask,
        private readonly SkipPlanTaskAction $skipTask,
        private readonly RescheduleTaskAction $rescheduleTask,
    ) {}

    /** Tasks for one day; defaults to today's list including missed work. */
    public function index(Request $request, StudyPlan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        $date = $request->query('date');

        $tasks = is_string($date) && $date !== ''
            ? $plan->tasks()->whereDate('date', $date)->orderBy('id')->get()
            : $this->todayTasks->handle($plan);

        return ApiResponse::item(StudyPlanTaskResource::collection($tasks)->resolve());
    }

    public function start(StudyPlan $plan, StudyPlanTask $task): JsonResponse
    {
        $this->authorize('update', $plan);

        if (! $task->type->isSupported()) {
            return ApiResponse::error('task_type_unavailable', 'Loại nhiệm vụ này chưa khả dụng.', 422);
        }

        try {
            $session = $this->startTask->handle($task);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('no_questions_available', $exception->getMessage(), 409);
        }

        return ApiResponse::item(
            new StudyPlanTaskResource($task->refresh()),
            200,
            ['session_id' => $session->getKey()],
        );
    }

    public function skip(StudyPlan $plan, StudyPlanTask $task): JsonResponse
    {
        $this->authorize('update', $plan);

        return ApiResponse::item(new StudyPlanTaskResource($this->skipTask->handle($task)));
    }

    public function reschedule(Request $request, StudyPlan $plan, StudyPlanTask $task): JsonResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$plan->exam_target_date->toDateString()],
        ]);

        return ApiResponse::item(
            new StudyPlanTaskResource($this->rescheduleTask->handle($task, Carbon::parse($validated['date']))),
        );
    }
}
