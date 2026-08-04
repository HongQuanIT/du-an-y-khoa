<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\StudyPlan\Events\StudyPlanActivity;

/**
 * Writes the tracking payload to the log pipeline until a product-analytics
 * sink exists (srs/00-nen-tang/06-tracking-analytics.md).
 */
final class RecordStudyPlanActivity
{
    public function handle(StudyPlanActivity $activity): void
    {
        Log::info('tracking', array_merge([
            'event' => $activity->name,
            'user_id' => $activity->plan->user_id,
            'study_plan_id' => $activity->plan->getKey(),
        ], $activity->context));
    }
}
