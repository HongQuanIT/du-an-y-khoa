<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * Hệ cơ quan — trục phân loại độc lập với môn học (VD: Hệ tim mạch).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property TaxonomyStatus $status
 * @property int $sort_order
 */
class OrganSystem extends Model
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

    /** @return BelongsToMany<Lesson, $this> */
    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(
            Lesson::class,
            'lesson_organ_system',
            'organ_system_id',
            'lesson_id',
        )->withPivot('sort_order')->withTimestamps()->orderBy('lessons.sort_order')->orderBy('lessons.name');
    }
}
