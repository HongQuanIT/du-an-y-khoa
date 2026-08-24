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

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_blueprint_topics')->withTimestamps();
    }

    /** @return BelongsToMany<MedicalTaxonomyNode, $this> */
    public function medicalTaxonomyNodes(): BelongsToMany
    {
        return $this->belongsToMany(
            MedicalTaxonomyNode::class,
            'core_topic_medical_taxonomy_nodes',
            'core_clinical_topic_id',
            'medical_taxonomy_node_id',
        )->withTimestamps();
    }
}
