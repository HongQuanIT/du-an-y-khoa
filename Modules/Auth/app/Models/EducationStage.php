<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EducationStage extends Model
{
    protected $fillable = ['code', 'name', 'is_graduated', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_graduated' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function learnerProfiles(): HasMany
    {
        return $this->hasMany(LearnerProfile::class);
    }
}
