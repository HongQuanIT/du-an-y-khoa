<?php

namespace Modules\Exam\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Exam\Enums\ExamStatus;
use Modules\QuestionBank\Models\Question;

class Exam extends Model
{
    protected $fillable = [
        'title',
        'description',
        'icon',
        'duration_minutes',
        'status',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'duration_minutes' => 'integer',
        'status' => ExamStatus::class,
    ];

    /**
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_question')
            ->withPivot('order')
            ->orderByPivot('order');
    }

    public function questionCount(): int
    {
        return (int) ($this->questions_count ?? $this->questions()->count());
    }

    public function isPublished(): bool
    {
        return $this->status === ExamStatus::Published;
    }
}
