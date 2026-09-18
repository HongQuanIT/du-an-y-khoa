<?php

namespace Modules\Exam\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Exam\Enums\ExamStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\Question;

class Exam extends Model
{
    protected $fillable = [
        'user_id',
        'blueprint_id',
        'title',
        'description',
        'icon',
        'duration_minutes',
        'status',
        'is_published',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'blueprint_id' => 'integer',
        'is_published' => 'boolean',
        'duration_minutes' => 'integer',
        'status' => ExamStatus::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Blueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_question')
            ->withPivot(['order', 'core_clinical_topic_id'])
            ->orderByPivot('order');
    }

    /** @return HasMany<ExamTopic, $this> */
    public function examTopics(): HasMany
    {
        return $this->hasMany(ExamTopic::class)->orderBy('sort_order');
    }

    public function configuredQuestionCount(): int
    {
        return (int) $this->examTopics()->sum('question_count');
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
