<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * @mixin StudyPlanTask
 */
final class StudyPlanTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'study_plan_task',
            'attributes' => [
                'study_plan_id' => $this->study_plan_id,
                'date' => $this->date->toDateString(),
                'task_type' => $this->type->value,
                'title' => $this->title(),
                'target' => $this->target,
                'done' => $this->done,
                'percent' => $this->percent(),
                'status' => $this->status->value,
                'estimated_minutes' => $this->estimatedMinutes(),
                'medical_taxonomy_node_ids' => $this->medicalTaxonomyNodeIds(),
                'topic_ids' => $this->topicIds(),
                'session_id' => $this->sessionId(),
            ],
        ];
    }
}
