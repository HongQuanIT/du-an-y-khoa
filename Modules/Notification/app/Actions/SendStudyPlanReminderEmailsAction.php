<?php

declare(strict_types=1);

namespace Modules\Notification\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Modules\Notification\Mail\StudyPlanReminderMail;
use Modules\Notification\Models\StudyPlanReminderLog;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Models\StudyPlanTask;

final class SendStudyPlanReminderEmailsAction
{
    use AsAction;

    public function handle(?Carbon $reminderDate = null): int
    {
        $date = ($reminderDate ?? Carbon::today())->copy()->startOfDay();
        $sent = 0;

        $userIds = StudyPlanTask::query()
            ->join('study_plans', 'study_plan_tasks.study_plan_id', '=', 'study_plans.id')
            ->where('study_plan_tasks.status', TaskStatus::Pending)
            ->whereDate('study_plan_tasks.date', '<=', $date)
            ->where('study_plans.status', PlanStatus::Active)
            ->distinct()
            ->pluck('study_plans.user_id');

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if ($user === null || ! $this->wantsPlanEmail($user)) {
                continue;
            }

            if ($this->alreadySent($user, $date)) {
                continue;
            }

            $tasks = $this->dueTasksFor($user, $date);
            if ($tasks->isEmpty()) {
                continue;
            }

            Mail::to($user)->send(new StudyPlanReminderMail($user, $tasks, $date));

            StudyPlanReminderLog::query()->create([
                'user_id' => $user->getKey(),
                'reminder_date' => $date->toDateString(),
                'task_count' => $tasks->count(),
                'sent_at' => Carbon::now(),
            ]);

            $sent++;
        }

        return $sent;
    }

    private function wantsPlanEmail(User $user): bool
    {
        $prefs = $user->notification_prefs ?? [];

        return (bool) ($prefs['email_plan'] ?? true);
    }

    private function alreadySent(User $user, Carbon $date): bool
    {
        return StudyPlanReminderLog::query()
            ->where('user_id', $user->getKey())
            ->whereDate('reminder_date', $date)
            ->exists();
    }

    /**
     * @return Collection<int, StudyPlanTask>
     */
    private function dueTasksFor(User $user, Carbon $date): Collection
    {
        return StudyPlanTask::query()
            ->with('plan')
            ->where('status', TaskStatus::Pending)
            ->whereDate('date', '<=', $date)
            ->whereHas('plan', function ($query) use ($user): void {
                $query
                    ->where('user_id', $user->getKey())
                    ->where('status', PlanStatus::Active);
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }
}
