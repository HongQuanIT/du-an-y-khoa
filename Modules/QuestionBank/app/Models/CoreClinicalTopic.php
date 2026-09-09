<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * @property int $id
 * @property int $blueprint_section_id
 * @property string $name
 * @property string $slug
 */
class CoreClinicalTopic extends Model
{
    protected $fillable = [
        'blueprint_section_id',
        'name',
        'slug',
        'code',
        'description',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'blueprint_section_id' => 'integer',
        'status' => TaxonomyStatus::class,
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<BlueprintSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(BlueprintSection::class, 'blueprint_section_id');
    }

    /** @return BelongsToMany<Lesson, $this> */
    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(
            Lesson::class,
            'core_topic_lessons',
            'core_clinical_topic_id',
            'lesson_id',
        )->withTimestamps();
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'core_topic_tags',
            'core_clinical_topic_id',
            'tag_id',
        )->withTimestamps();
    }
}
