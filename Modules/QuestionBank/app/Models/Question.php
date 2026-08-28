<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;
use Modules\QuestionBank\Database\Factories\QuestionFactory;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;

/**
 * A single QBank question (reference implementation of the module pattern).
 *
 * @property string $id
 * @property string|null $code
 * @property string $stem
 * @property string|null $stem_image_path
 * @property string|null $explanation
 * @property array<int, string>|null $key_info
 * @property string|null $attending_tip
 * @property Difficulty $difficulty
 * @property QuestionStatus $status
 * @property bool $is_free
 * @property bool $exam_flag
 * @property int $version
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $reviewer_id
 * @property string|null $rejection_reason
 * @property string|null $cloned_from_id
 * @property int|null $cloned_from_version
 * @property array<string, mixed>|null $stats_cache
 * @property Carbon|null $stats_updated_at
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
        'code',
        'stem',
        'stem_image_path',
        'explanation',
        'key_info',
        'attending_tip',
        'difficulty',
        'status',
        'is_free',
        'exam_flag',
        'created_by',
        'updated_by',
        'reviewer_id',
        'rejection_reason',
        'cloned_from_id',
        'cloned_from_version',
        'stats_cache',
        'stats_updated_at',
    ];

    protected $casts = [
        'difficulty' => Difficulty::class,
        'status' => QuestionStatus::class,
        'key_info' => 'array',
        'is_free' => 'boolean',
        'exam_flag' => 'boolean',
        'version' => 'integer',
        'cloned_from_version' => 'integer',
        'stats_cache' => 'array',
        'stats_updated_at' => 'datetime',
    ];

    /**
     * Admin list analytics — only read this rollup, never COUNT attempts live.
     *
     * @return array{
     *     total_attempts: int,
     *     correct_rate: float|null,
     *     total_reports: int
     * }
     */
    public function listStats(): array
    {
        $detail = $this->detailStats();

        return [
            'total_attempts' => $detail['total_attempts'],
            'correct_rate' => $detail['correct_rate'],
            'total_reports' => $detail['total_reports'],
        ];
    }

    /**
     * Full rollup for the question analytics detail page (SRS §5.4).
     *
     * @return array{
     *     total_attempts: int,
     *     study_mode_attempts: int,
     *     exam_mode_attempts: int,
     *     correct_attempts: int,
     *     incorrect_attempts: int,
     *     correct_rate: float|null,
     *     average_score: float|null,
     *     total_reports: int,
     *     reports_by_reason: array<string, int>,
     *     quality_hint: string|null
     * }
     */
    public function detailStats(): array
    {
        $cache = $this->stats_cache ?? [];

        $totalAttempts = (int) ($cache['total_attempts'] ?? 0);
        $correctAttempts = (int) ($cache['correct_attempts'] ?? 0);
        $incorrectAttempts = (int) ($cache['incorrect_attempts'] ?? max(0, $totalAttempts - $correctAttempts));
        $correctRate = array_key_exists('correct_rate', $cache) && $cache['correct_rate'] !== null
            ? (float) $cache['correct_rate']
            : ($totalAttempts > 0 ? $correctAttempts / $totalAttempts : null);
        $averageScore = array_key_exists('average_score', $cache) && $cache['average_score'] !== null
            ? (float) $cache['average_score']
            : null;
        $reportsByReason = is_array($cache['reports_by_reason'] ?? null)
            ? array_map('intval', $cache['reports_by_reason'])
            : [];

        return [
            'total_attempts' => $totalAttempts,
            'study_mode_attempts' => (int) ($cache['study_mode_attempts'] ?? 0),
            'exam_mode_attempts' => (int) ($cache['exam_mode_attempts'] ?? 0),
            'correct_attempts' => $correctAttempts,
            'incorrect_attempts' => $incorrectAttempts,
            'correct_rate' => $correctRate,
            'average_score' => $averageScore,
            'total_reports' => (int) ($cache['total_reports'] ?? 0),
            'reports_by_reason' => $reportsByReason,
            'quality_hint' => $this->qualityHintFromRate($correctRate, $totalAttempts),
        ];
    }

    private function qualityHintFromRate(?float $correctRate, int $totalAttempts): ?string
    {
        if ($correctRate === null || $totalAttempts < 20) {
            return null;
        }

        return match (true) {
            $correctRate >= 0.9 => 'Có thể quá dễ — cân nhắc tăng độ khó hoặc siết đáp án nhiễu.',
            $correctRate <= 0.25 => 'Có thể quá khó hoặc mơ hồ — kiểm tra stem/đáp án/giải thích.',
            default => null,
        };
    }

    /** @return BelongsToMany<CoreClinicalTopic, $this> */
    public function coreClinicalTopics(): BelongsToMany
    {
        return $this->belongsToMany(CoreClinicalTopic::class, 'question_blueprint_topics')->withTimestamps();
    }

    /** @return BelongsToMany<MedicalTaxonomyNode, $this> */
    public function medicalTaxonomyNodes(): BelongsToMany
    {
        return $this->belongsToMany(MedicalTaxonomyNode::class, 'question_medical_topics')
            ->withPivot(['relationship_type', 'is_primary'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'question_tags')->withTimestamps();
    }

    /** @return HasMany<QuestionHint, $this> */
    public function hints(): HasMany
    {
        return $this->hasMany(QuestionHint::class, 'question_id')->orderBy('sort_order');
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id');
    }

    /** @return HasMany<QuestionFeedback, $this> */
    public function feedback(): HasMany
    {
        return $this->hasMany(QuestionFeedback::class, 'question_id');
    }

    /** @return HasOne<QuestionFeedback, $this> */
    public function latestFeedback(): HasOne
    {
        return $this->hasOne(QuestionFeedback::class, 'question_id')->latestOfMany();
    }

    /** @return HasMany<QuestionVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(QuestionVersion::class)->latest('version');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_id');
    }

    /** @return HasMany<QuestionReviewRequest, $this> */
    public function reviewRequests(): HasMany
    {
        return $this->hasMany(QuestionReviewRequest::class);
    }

    /** @return MorphMany<AuditLog, $this> */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /** @return HasOne<QuestionReviewRequest, $this> */
    public function pendingReviewRequest(): HasOne
    {
        return $this->hasOne(QuestionReviewRequest::class)
            ->where('status', QuestionReviewStatus::Pending->value)
            ->latestOfMany();
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

        $coreClinicalTopicIds = ($this->relationLoaded('coreClinicalTopics')
            ? $this->coreClinicalTopics
            : $this->coreClinicalTopics()->get())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $medicalTaxonomyNodeIds = ($this->relationLoaded('medicalTaxonomyNodes')
            ? $this->medicalTaxonomyNodes
            : $this->medicalTaxonomyNodes()->get())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $tagIds = ($this->relationLoaded('tags') ? $this->tags : $this->tags()->get())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return [
            'id' => $this->getKey(),
            'stem' => $plainStem,
            'difficulty' => $this->difficulty->value,
            'core_clinical_topic_ids' => $coreClinicalTopicIds,
            'medical_taxonomy_node_ids' => $medicalTaxonomyNodeIds,
            'tag_ids' => $tagIds,
            'is_free' => $this->is_free,
        ];
    }

    /** Only published questions are searchable. */
    public function shouldBeSearchable(): bool
    {
        return $this->status === QuestionStatus::Published;
    }

    public function isExamPool(): bool
    {
        return $this->exam_flag && $this->status === QuestionStatus::Private;
    }

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }
}
