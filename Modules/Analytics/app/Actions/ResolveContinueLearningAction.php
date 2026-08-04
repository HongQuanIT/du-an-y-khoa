<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\StudyPlan\Actions\ListTodayTasksAction;
use Modules\StudyPlan\Models\StudyPlanTask;
use Modules\StudyPlan\Repositories\StudyPlanRepository;

/**
 * Use case: what the dashboard's "Continue learning" card should point at.
 *
 * Priority per srs/modules/03: an unfinished session first, then today's plan
 * task, otherwise nothing to resume.
 *
 * @phpstan-type ContinueCard array{label: string, title: string, hint: string, progress: int, url: string}
 */
final class ResolveContinueLearningAction
{
    use AsAction;

    public function __construct(
        private readonly StudyPlanRepository $plans,
        private readonly ListTodayTasksAction $todayTasks,
    ) {}

    /**
     * @return ContinueCard|null
     */
    public function handle(User $user): ?array
    {
        return $this->fromPausedSession($user) ?? $this->fromPlanTask($user);
    }

    /**
     * @return ContinueCard|null
     */
    private function fromPausedSession(User $user): ?array
    {
        $session = QuestionSession::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [SessionStatus::Active, SessionStatus::Paused])
            ->where('answered_count', '>', 0)
            ->latest('updated_at')
            ->first();

        if ($session === null) {
            return null;
        }

        $task = $this->taskBehind($session);

        return [
            'label' => 'Đang học dở',
            'title' => $task?->title() ?? 'Phiên luyện tập',
            'hint' => sprintf(
                'Câu %d/%d · Chế độ %s',
                $session->answered_count + 1,
                $session->total,
                $session->mode->value,
            ),
            'progress' => $session->total > 0
                ? (int) round($session->answered_count / $session->total * 100)
                : 0,
            'url' => $task !== null
                ? route('study-plan.session', [$task->study_plan_id, $task])
                : route('qbank.session'),
        ];
    }

    /**
     * @return ContinueCard|null
     */
    private function fromPlanTask(User $user): ?array
    {
        $plan = $this->plans->currentFor($user);

        if ($plan === null || ! $plan->isActive()) {
            return null;
        }

        $task = $this->todayTasks->handle($plan)
            ->first(fn (StudyPlanTask $task) => ! $task->isDone() && $task->type->isSupported());

        if ($task === null) {
            return null;
        }

        return [
            'label' => 'Nhiệm vụ hôm nay',
            'title' => $task->title(),
            'hint' => sprintf('%s · khoảng %d phút', $plan->name, $task->estimatedMinutes()),
            'progress' => $task->percent(),
            'url' => route('study-plan.session', [$plan, $task]),
        ];
    }

    private function taskBehind(QuestionSession $session): ?StudyPlanTask
    {
        $taskId = $session->filters['study_plan_task_id'] ?? null;

        return is_numeric($taskId) ? StudyPlanTask::find((int) $taskId) : null;
    }
}
