<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $type
 */
class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => TaxonomyStatus::class,
    ];

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_tags')->withTimestamps();
    }
}
