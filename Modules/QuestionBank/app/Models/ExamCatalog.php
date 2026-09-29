<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Auth\Models\Profession;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * Kỳ thi catalog. The optional blueprint is the ma trận used only to build an exam paper.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property string|null $description
 * @property int|null $blueprint_id
 * @property TaxonomyStatus $status
 * @property int $sort_order
 */
class ExamCatalog extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'blueprint_id',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'blueprint_id' => 'integer',
        'status' => TaxonomyStatus::class,
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Blueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /** @return BelongsToMany<Profession, $this> */
    public function professions(): BelongsToMany
    {
        return $this->belongsToMany(Profession::class, 'exam_catalog_professions')->withTimestamps();
    }

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_exam_catalogs')->withTimestamps();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<int>
     */
    public static function idsForSnapshot(array $snapshot): array
    {
        if (array_key_exists('exam_catalog_ids', $snapshot)) {
            $ids = array_map('intval', (array) $snapshot['exam_catalog_ids']);

            return self::query()
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        if (! array_key_exists('blueprint_ids', $snapshot)) {
            return [];
        }

        return self::query()
            ->whereIn('blueprint_id', array_map('intval', (array) $snapshot['blueprint_ids']))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
};
