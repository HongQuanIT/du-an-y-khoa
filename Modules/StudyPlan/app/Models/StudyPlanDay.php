<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\StudyPlan\Enums\TaskStatus;

final class StudyPlanDay extends Model
{
    protected $fillable = [
        'study_plan_id',
        'date',
        'question_count',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'question_count' => 'integer',
        'status' => TaskStatus::class,
    ];

    /** @return BelongsTo<StudyPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class, 'study_plan_id');
    }

    /** @return HasMany<StudyPlanQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(StudyPlanQuestion::class);
    }
}
