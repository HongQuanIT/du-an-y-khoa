<?php

declare(strict_types=1);

namespace Modules\Exam\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Models\CoreClinicalTopic;

/**
 * @property int $id
 * @property int $exam_id
 * @property int $core_clinical_topic_id
 * @property int $question_count
 * @property int $sort_order
 */
class ExamTopic extends Model
{
    protected $fillable = [
        'exam_id',
        'core_clinical_topic_id',
        'question_count',
        'sort_order',
    ];

    protected $casts = [
        'exam_id' => 'integer',
        'core_clinical_topic_id' => 'integer',
        'question_count' => 'integer',
        'sort_order' => 'integer',
    ];

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
