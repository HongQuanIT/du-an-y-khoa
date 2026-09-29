<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\Question;

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

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_professions')->withTimestamps();
    }

    /** @return BelongsToMany<Blueprint, $this> */
    public function blueprints(): BelongsToMany
    {
        return $this->belongsToMany(Blueprint::class, 'blueprint_professions')->withTimestamps();
    }

    /** @return BelongsToMany<ExamCatalog, $this> */
    public function examCatalogs(): BelongsToMany
    {
        return $this->belongsToMany(ExamCatalog::class, 'exam_catalog_professions')->withTimestamps();
    }
}
