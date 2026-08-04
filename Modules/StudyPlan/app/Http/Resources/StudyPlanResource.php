<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\StudyPlan\Models\StudyPlan;

/**
 * @mixin StudyPlan
 */
final class StudyPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'study_plan',
            'attributes' => [
                'name' => $this->name,
                'exam_key' => $this->exam_key,
                'exam_target_date' => $this->exam_target_date->toDateString(),
                'days_until_exam' => $this->daysUntilExam(),
                'daily_goal_questions' => $this->daily_goal_questions,
                'daily_goal_minutes' => $this->daily_goal_minutes,
                'topic_scope' => $this->scopeFilters(),
                'study_days' => $this->studyWeekdays(),
                'strategy' => $this->strategy->value,
                'status' => $this->status->value,
                'progress' => $this->progress_cache ?? [],
                'replanned_at' => $this->replanned_at?->toIso8601String(),
                'created_at' => $this->created_at?->toIso8601String(),
            ],
            'relationships' => [
                'tasks' => StudyPlanTaskResource::collection($this->whenLoaded('tasks')),
            ],
        ];
    }
}
