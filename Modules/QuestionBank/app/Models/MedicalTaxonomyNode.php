<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * @property int $id
 * @property int $medical_taxonomy_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 */
class MedicalTaxonomyNode extends Model
{
    protected $fillable = [
        'medical_taxonomy_id',
        'parent_id',
        'name',
        'slug',
        'code',
        'node_type',
        'description',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'medical_taxonomy_id' => 'integer',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'status' => TaxonomyStatus::class,
    ];

    /** @return BelongsTo<MedicalTaxonomy, $this> */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(MedicalTaxonomy::class, 'medical_taxonomy_id');
    }

    /** @return BelongsTo<MedicalTaxonomyNode, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<MedicalTaxonomyNode, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_medical_topics')
            ->withPivot(['relationship_type', 'is_primary'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<CoreClinicalTopic, $this> */
    public function coreClinicalTopics(): BelongsToMany
    {
        return $this->belongsToMany(
            CoreClinicalTopic::class,
            'core_topic_medical_taxonomy_nodes',
            'medical_taxonomy_node_id',
            'core_clinical_topic_id',
        )->withTimestamps();
    }
}
