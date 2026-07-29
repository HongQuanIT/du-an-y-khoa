<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Database\Factories\QuestionOptionFactory;

/**
 * A single answer choice belonging to a question.
 *
 * @property int $id
 * @property string $question_id
 * @property string $label
 * @property string $content
 * @property bool $is_correct
 * @property string|null $explanation
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class QuestionOption extends Model
{
    /** @use HasFactory<QuestionOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'question_id',
        'label',
        'content',
        'is_correct',
        'explanation',
        'order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'order' => 'integer',
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    protected static function newFactory(): QuestionOptionFactory
    {
        return QuestionOptionFactory::new();
    }
}
