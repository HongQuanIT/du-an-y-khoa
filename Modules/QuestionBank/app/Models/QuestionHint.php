<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * Ordered study hint belonging to a single question (not a concept/tag).
 *
 * @property int $id
 * @property string $question_id
 * @property string $content
 * @property int $sort_order
 * @property TaxonomyStatus|string $status
 */
class QuestionHint extends Model
{
    protected $fillable = [
        'question_id',
        'content',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'status' => TaxonomyStatus::class,
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
