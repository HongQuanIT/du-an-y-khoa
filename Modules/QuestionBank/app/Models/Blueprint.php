<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\Profession;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property string|null $description
 * @property TaxonomyStatus $status
 * @property int $sort_order
 * @property int|null $total_questions
 * @property int $difficulty_easy_percent
 * @property int $difficulty_medium_percent
 * @property int $difficulty_hard_percent
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
        'total_questions',
        'difficulty_easy_percent',
        'difficulty_medium_percent',
        'difficulty_hard_percent',
    ];

    protected $casts = [
        'status' => TaxonomyStatus::class,
        'sort_order' => 'integer',
        'total_questions' => 'integer',
        'difficulty_easy_percent' => 'integer',
        'difficulty_medium_percent' => 'integer',
        'difficulty_hard_percent' => 'integer',
    ];

    /** @return HasMany<BlueprintSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(BlueprintSection::class)->orderBy('sort_order')->orderBy('name');
    }

    /** @return BelongsToMany<Profession, $this> */
    public function professions(): BelongsToMany
    {
        return $this->belongsToMany(Profession::class, 'blueprint_professions')->withTimestamps();
    }

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_blueprints')->withTimestamps();
    }

    /** @return array{easy: int, medium: int, hard: int} */
    public function difficultyWeights(): array
    {
        return [
            'easy' => $this->difficulty_easy_percent ?? 40,
            'medium' => $this->difficulty_medium_percent ?? 30,
            'hard' => $this->difficulty_hard_percent ?? 30,
        ];
    }
}
