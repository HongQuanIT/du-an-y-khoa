<?php

declare(strict_types=1);

namespace Modules\Exam\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Models\CoreClinicalTopic;

/**
 * @property int $id
 * @property int $exam_id
 * @property int $core_clinical_topic_id
 * @property int $question_count
 * @property array<string, int>|null $difficulty_counts
 * @property int $sort_order
 */
class ExamTopic extends Model
{
    protected $fillable = [
        'exam_id',
        'core_clinical_topic_id',
        'question_count',
        'difficulty_counts',
        'sort_order',
    ];

    protected $casts = [
        'exam_id' => 'integer',
        'core_clinical_topic_id' => 'integer',
        'question_count' => 'integer',
        'difficulty_counts' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * @return array<string, int>
     */
    public static function emptyDifficultyCounts(): array
    {
        $counts = [];
        foreach (Difficulty::cases() as $case) {
            $counts[$case->value] = 0;
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, int>
     */
    public static function normalizeDifficultyCounts(?array $raw, ?int $fallbackTotal = null): array
    {
        $normalized = self::emptyDifficultyCounts();
        $hasAny = false;

        foreach ($normalized as $key => $_) {
            $value = max(0, (int) ($raw[$key] ?? 0));
            $normalized[$key] = $value;
            if ($value > 0) {
                $hasAny = true;
            }
        }

        if (! $hasAny && $fallbackTotal !== null && $fallbackTotal > 0) {
            $normalized[Difficulty::Medium->value] = $fallbackTotal;
        }

        return $normalized;
    }

    /**
     * @param  array<string, int>  $counts
     */
    public static function sumDifficultyCounts(array $counts): int
    {
        return (int) array_sum($counts);
    }

    /**
     * @return array<string, int>
     */
    public function difficultyCountsOrEmpty(): array
    {
        return self::normalizeDifficultyCounts($this->difficulty_counts, $this->question_count);
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<CoreClinicalTopic, $this> */
    public function coreClinicalTopic(): BelongsTo
    {
        return $this->belongsTo(CoreClinicalTopic::class);
    }
}
