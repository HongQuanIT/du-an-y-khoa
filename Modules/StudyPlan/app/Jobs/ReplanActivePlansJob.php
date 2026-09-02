<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Jobs;

use App\Support\Queue\Concerns\HasQueueDisplayName;
use App\Support\Queue\QueueName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\StudyPlan\Actions\ReplanStudyPlanAction;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Enums\PlanStrategy;
use Modules\StudyPlan\Models\StudyPlan;
use Throwable;

/**
 * Nightly adaptive re-balance for every active adaptive plan.
 *
 * Plans are processed in chunks and one failure never blocks the rest, so a
 * single broken plan cannot stall the whole run.
 */
final class ReplanActivePlansJob implements ShouldBeUnique, ShouldQueue
{
    use HasQueueDisplayName;
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /** Một lần replan nightly — lock 2 giờ. */
    public int $uniqueFor = 7200;

    public function __construct()
    {
        $this->onQueue(QueueName::StudyPlan->value);
    }

    public function displayName(): string
    {
        return 'study-plan:replan-active-adaptive';
    }

    public function uniqueId(): string
    {
        return 'replan-active-adaptive';
    }

    public function handle(ReplanStudyPlanAction $replan): void
    {
        StudyPlan::query()
            ->where('status', PlanStatus::Active)
            ->where('strategy', PlanStrategy::Adaptive)
            ->chunkById(100, function ($plans) use ($replan): void {
                foreach ($plans as $plan) {
                    try {
                        $replan->handle($plan);
                    } catch (Throwable $exception) {
                        Log::error('Replan kế hoạch thất bại', [
                            'study_plan_id' => $plan->getKey(),
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return $this->featureTags('study-plan', 'replan', 'adaptive');
    }
}
