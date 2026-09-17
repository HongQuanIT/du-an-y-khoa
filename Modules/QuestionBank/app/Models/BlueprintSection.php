<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * @property int $id
 * @property int $blueprint_id
 * @property string $name
 * @property string $slug
 * @property float|null $weight_min
 * @property float|null $weight_max
 */
class BlueprintSection extends Model
{
    protected $fillable = [
        'blueprint_id',
        'name',
        'slug',
        'code',
        'description',
        'status',
        'sort_order',
        'weight_min',
        'weight_max',
    ];

    protected $casts = [
        'blueprint_id' => 'integer',
        'status' => TaxonomyStatus::class,
        'sort_order' => 'integer',
        'weight_min' => 'float',
        'weight_max' => 'float',
    ];

    /** @return BelongsTo<Blueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /** @return HasMany<CoreClinicalTopic, $this> */
    public function coreClinicalTopics(): HasMany
    {
        return $this->hasMany(CoreClinicalTopic::class)->orderBy('sort_order')->orderBy('name');
    }
}
