<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * Bài học — đơn vị kiến thức chuẩn; câu hỏi gắn vào bài học.
 *
 * Một bài học có thể gắn 0 hoặc nhiều môn học và 0 hoặc nhiều hệ cơ quan (hai trục độc lập).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property TaxonomyStatus $status
 * @property int $sort_order
 */
class Lesson extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'status' => TaxonomyStatus::class,
        'sort_order' => 'integer',
    ];

    /** @return BelongsToMany<Subject, $this> */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'lesson_subject',
            'lesson_id',
            'subject_id',
        )->withPivot('sort_order')->withTimestamps()->orderBy('subjects.sort_order')->orderBy('subjects.name');
    }

    /** @return BelongsToMany<OrganSystem, $this> */
    public function organSystems(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganSystem::class,
            'lesson_organ_system',
            'lesson_id',
            'organ_system_id',
        )->withPivot('sort_order')->withTimestamps()->orderBy('organ_systems.sort_order')->orderBy('organ_systems.name');
    }

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_lesson')
            ->withTimestamps();
    }

    /** @return BelongsToMany<CoreClinicalTopic, $this> */
    public function coreClinicalTopics(): BelongsToMany
    {
        return $this->belongsToMany(
            CoreClinicalTopic::class,
            'core_topic_lessons',
            'lesson_id',
            'core_clinical_topic_id',
        )->withTimestamps();
    }
}
