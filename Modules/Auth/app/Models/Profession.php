<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Profession extends Model
{
    protected $fillable = [
        'code', 'name', 'requires_education_stage', 'defaults_to_graduated', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_education_stage' => 'boolean',
            'defaults_to_graduated' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function learnerProfiles(): HasMany
    {
        return $this->hasMany(LearnerProfile::class);
    }
}
