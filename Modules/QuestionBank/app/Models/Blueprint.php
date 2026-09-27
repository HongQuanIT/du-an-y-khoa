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
    ];

    protected $casts = [
        'status' => TaxonomyStatus::class,
        'sort_order' => 'integer',
        'total_questions' => 'integer',
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
}
