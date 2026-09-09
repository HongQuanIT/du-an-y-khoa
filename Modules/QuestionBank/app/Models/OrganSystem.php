<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * Hệ cơ quan — cấp cao nhất của danh mục phân loại (VD: Hệ tim mạch).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property string|null $description
 * @property TaxonomyStatus $status
 * @property int $sort_order
 */
class OrganSystem extends Model
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

    /** @return BelongsToMany<Subject, $this> */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'subject_organ_system',
            'organ_system_id',
            'subject_id',
        )->withPivot('sort_order')->withTimestamps()->orderBy('subjects.sort_order')->orderBy('subjects.name');
    }

    /**
     * Lessons reachable through this organ system's subjects.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Lesson>
     */
    public function lessonsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Lesson::query()->whereHas(
            'subjects',
            fn ($subjects) => $subjects->whereHas(
                'organSystems',
                fn ($systems) => $systems->whereKey($this->getKey()),
            ),
        );
    }
}
