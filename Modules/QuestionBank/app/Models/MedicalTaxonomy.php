<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 */
class MedicalTaxonomy extends Model
{
    public const CANONICAL_CODE = 'medlearn-medical-taxonomy';

    /** @deprecated Bộ migrate từ topics cũ — đã loại bỏ. */
    public const LEGACY_MIGRATED_CODE = 'medlearn';

    protected $fillable = [
        'name',
        'code',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => TaxonomyStatus::class,
    ];

    /** @return HasMany<MedicalTaxonomyNode, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(MedicalTaxonomyNode::class)->orderBy('sort_order')->orderBy('name');
    }

    /** @return HasMany<MedicalTaxonomyNode, $this> */
    public function rootNodes(): HasMany
    {
        return $this->nodes()->whereNull('parent_id');
    }

    public static function canonical(): ?self
    {
        return static::query()->where('code', self::CANONICAL_CODE)->first();
    }
}
