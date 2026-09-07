<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Models\Question;

final class StudyPlanQuestion extends Model
{
    protected $fillable = [
        'study_plan_day_id',
        'question_id',
        'order',
        'source_status',
        'high_yield_score',
    ];

    protected $casts = [
        'order' => 'integer',
        'high_yield_score' => 'float',
    ];

    /** @return BelongsTo<StudyPlanDay, $this> */
    public function day(): BelongsTo
    {
        return $this->belongsTo(StudyPlanDay::class, 'study_plan_day_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
