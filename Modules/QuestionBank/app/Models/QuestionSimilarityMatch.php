<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Enums\DuplicateSeverity;

/**
 * Persisted near-duplicate pair (question_id_low < question_id_high).
 *
 * @property int $id
 * @property string $question_id_low
 * @property string $question_id_high
 * @property float $score
 * @property DuplicateSeverity $severity
 * @property array<string, mixed>|null $signals
 * @property Carbon|null $detected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class QuestionSimilarityMatch extends Model
{
    protected $fillable = [
        'question_id_low',
        'question_id_high',
        'score',
        'severity',
        'signals',
        'detected_at',
    ];

    protected $casts = [
        'score' => 'float',
        'severity' => DuplicateSeverity::class,
        'signals' => 'array',
        'detected_at' => 'datetime',
    ];

    /** @return BelongsTo<Question, $this> */
    public function questionLow(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id_low');
    }

    /** @return BelongsTo<Question, $this> */
    public function questionHigh(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id_high');
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function orderedIds(string $a, string $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    public function otherQuestionId(string $questionId): string
    {
        return $this->question_id_low === $questionId
            ? $this->question_id_high
            : $this->question_id_low;
    }
}
