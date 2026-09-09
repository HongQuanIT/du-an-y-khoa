<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * Môn học — gom các bài học; có thể thuộc nhiều hệ cơ quan.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property string|null $description
 * @property TaxonomyStatus $status
 * @property int $sort_order
 */
class Subject extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'status' => TaxonomyStatus::class,
        'sort_order' => 'integer',
    ];

    /** @return BelongsToMany<OrganSystem, $this> */
    public function organSystems(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganSystem::class,
            'subject_organ_system',
            'subject_id',
            'organ_system_id',
        )->withPivot('sort_order')->withTimestamps()->orderBy('organ_systems.sort_order')->orderBy('organ_systems.name');
    }

    /** @return BelongsToMany<Lesson, $this> */
    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(
            Lesson::class,
            'lesson_subject',
            'subject_id',
            'lesson_id',
        )->withPivot('sort_order')->withTimestamps()->orderBy('lessons.sort_order')->orderBy('lessons.name');
    }
}
