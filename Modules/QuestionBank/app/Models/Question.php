<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;
use Modules\QuestionBank\Database\Factories\QuestionFactory;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;

/**
 * A single QBank question (reference implementation of the module pattern).
 *
 * @property string $id
 * @property string $stem
 * @property string|null $explanation
 * @property Difficulty $difficulty
 * @property QuestionStatus $status
 * @property int|null $topic_id
 * @property bool $is_free
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    use HasUuids;
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'stem',
        'explanation',
        'difficulty',
        'status',
        'topic_id',
        'is_free',
    ];

    protected $casts = [
        'difficulty' => Difficulty::class,
        'status' => QuestionStatus::class,
        'is_free' => 'boolean',
        'version' => 'integer',
    ];

    /**
     * Meilisearch document. Only index what search/faceting needs.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'stem' => $this->stem,
            'difficulty' => $this->difficulty->value,
            'topic_id' => $this->topic_id,
            'is_free' => $this->is_free,
        ];
    }

    /** Only published questions are searchable. */
    public function shouldBeSearchable(): bool
    {
        return $this->status === QuestionStatus::Published;
    }

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }
}
