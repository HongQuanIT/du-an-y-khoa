<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property string|null $description
 * @property TaxonomyStatus $status
 * @property int $sort_order
 */
class Blueprint extends Model
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

    /** @return HasMany<BlueprintSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(BlueprintSection::class)->orderBy('sort_order')->orderBy('name');
    }
}
