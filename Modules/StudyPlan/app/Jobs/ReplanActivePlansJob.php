<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Jobs;

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
final class ReplanActivePlansJob implements ShouldQueue
{
    use Queueable;

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
}
