<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Modules\QuestionBank\Database\Factories\QuestionFactory;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Illuminate\Support\Facades\Storage;

/**
 * A single QBank question (reference implementation of the module pattern).
 *
 * @property string $id
 * @property string $stem
 * @property string|null $stem_image_path
 * @property string|null $explanation
 * @property array<int, string>|null $key_info
 * @property string|null $attending_tip
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
        'stem_image_path',
        'explanation',
        'key_info',
        'attending_tip',
        'difficulty',
        'status',
        'topic_id',
        'is_free',
    ];

    protected $casts = [
        'difficulty' => Difficulty::class,
        'status' => QuestionStatus::class,
        'key_info' => 'array',
        'is_free' => 'boolean',
        'version' => 'integer',
    ];

    /** @return BelongsTo<Topic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id');
    }

    public function stemImageUrl(): ?string
    {
        $path = $this->getAttributes()['stem_image_path'] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /** @return HasMany<QuestionScope, $this> */
    public function scopes(): HasMany
    {
        return $this->hasMany(QuestionScope::class, 'question_id');
    }

    /**
     * Options in a stable random order for one study session.
     *
     * Labels are reassigned A/B/C… for display; grading still uses option ids.
     *
     * @return Collection<int, QuestionOption>
     */
    public function optionsForSession(string $sessionKey): Collection
    {
        $options = ($this->relationLoaded('options')
            ? $this->options
            : $this->options()->orderBy('order')->get()
        )->values()->all();

        $seed = hexdec(substr(hash('sha256', $sessionKey.'|'.$this->getKey()), 0, 8));
        for ($i = count($options) - 1; $i > 0; $i--) {
            $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
            $j = $seed % ($i + 1);
            [$options[$i], $options[$j]] = [$options[$j], $options[$i]];
        }

        $labels = range('A', 'Z');

        return collect($options)->values()->map(function (QuestionOption $option, int $index) use ($labels) {
            $option->setAttribute('label', $labels[$index] ?? (string) ($index + 1));

            return $option;
        });
    }

    /**
     * Meilisearch document. Only index what search/faceting needs.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $plainStem = strip_tags(html_entity_decode(
            (string) $this->stem,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        ));
        $plainStem = trim(preg_replace('/\s+/u', ' ', $plainStem) ?? $plainStem);

        return [
            'id' => $this->getKey(),
            'stem' => $plainStem,
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
