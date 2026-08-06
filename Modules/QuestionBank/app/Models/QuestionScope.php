<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Enums\QuestionScopeType;

/** One exam/article/symptom assignment attached to a question. */
final class QuestionScope extends Model
{
    protected $fillable = [
        'question_id',
        'scope_type',
        'scope_key',
    ];

    protected $casts = [
        'scope_type' => QuestionScopeType::class,
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
