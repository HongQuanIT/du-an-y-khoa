<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Enums\Role;
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
use Modules\QuestionBank\Enums\InstructorReviewDecision;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Support\QuestionCodeAllocator;
use Modules\QuestionBank\Support\QuestionFilterBuilder;
use Modules\QuestionBank\Support\ServePublishedQuestion;

/**
 * A single QBank question (reference implementation of the module pattern).
 *
 * @property string $id
 * @property string $code
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
 * @property int|null $instructor_id
 * @property int $instructor_review_cycle
 * @property int|null $instructor_1_id
 * @property string|null $instructor_1_decision
 * @property int|null $instructor_2_id
 * @property string|null $instructor_2_decision
 * @property int|null $publisher_id
 * @property int|null $published_version
 * @property string|null $rejection_reason
 * @property string|null $rejected_by_role
 * @property string|null $cloned_from_id
 * @property int|null $cloned_from_version
 * @property string|null $import_batch_id
 * @property array<string, mixed>|null $stats_cache
 * @property Carbon|null $stats_updated_at
 * @property string|null $content_fingerprint
 * @property Carbon|null $similarity_checked_at
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

    protected $attributes = [
        'version' => 0,
        'instructor_review_cycle' => 0,
    ];

    /**
     * Code is assigned on create and never mass-assigned afterward.
     *
     * @var list<string>
     */
    protected $fillable = [
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
        'instructor_id',
        'instructor_review_cycle',
        'instructor_1_id',
        'instructor_1_decision',
        'instructor_2_id',
        'instructor_2_decision',
        'publisher_id',
        'published_version',
        'rejection_reason',
        'rejected_by_role',
        'cloned_from_id',
        'cloned_from_version',
        'import_batch_id',
        'stats_cache',
        'stats_updated_at',
        'content_fingerprint',
        'similarity_checked_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Question $question): void {
            if (blank($question->code)) {
                $question->code = app(QuestionCodeAllocator::class)->allocate();

                return;
            }

            if (preg_match('/^Q(\d+)$/', (string) $question->code, $matches) === 1) {
                app(QuestionCodeAllocator::class)->ensureAtLeast(((int) $matches[1]) + 1);
            }
        });

        static::updating(function (Question $question): void {
            $original = $question->getOriginal('code');
            if (filled($original) && $question->isDirty('code')) {
                $question->code = $original;
            }
        });
    }

    protected $casts = [
        'difficulty' => Difficulty::class,
        'status' => QuestionStatus::class,
        'key_info' => 'array',
        'is_free' => 'boolean',
        'exam_flag' => 'boolean',
        'version' => 'integer',
        'instructor_review_cycle' => 'integer',
        'published_version' => 'integer',
        'cloned_from_version' => 'integer',
        'stats_cache' => 'array',
        'stats_updated_at' => 'datetime',
        'similarity_checked_at' => 'datetime',
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

    /**
     * Core clinical topics projected from medical taxonomy ↔ blueprint mapping.
     *
     * @return Collection<int, CoreClinicalTopic>
     */
    public function inferredCoreClinicalTopics(): Collection
    {
        return app(QuestionFilterBuilder::class)
            ->inferredCoreClinicalTopicsForQuestion($this);
    }

    /** @return BelongsToMany<Lesson, $this> */
    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'question_lesson')
            ->withTimestamps();
    }

    /**
     * Subjects inferred from attached lessons (lesson_subject).
     *
     * @return Collection<int, Subject>
     */
    public function inferredSubjects(): Collection
    {
        $lessons = $this->relationLoaded('lessons')
            ? $this->lessons
            : $this->lessons()->with('subjects')->get();

        return $lessons
            ->flatMap(function (Lesson $lesson): Collection {
                return $lesson->relationLoaded('subjects')
                    ? $lesson->subjects
                    : $lesson->subjects()->get();
            })
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Organ systems inferred via lessons → subjects → organ systems.
     *
     * @return Collection<int, OrganSystem>
     */
    public function inferredOrganSystems(): Collection
    {
        return $this->inferredSubjects()
            ->flatMap(function (Subject $subject): Collection {
                return $subject->relationLoaded('organSystems')
                    ? $subject->organSystems
                    : $subject->organSystems()->get();
            })
            ->unique('id')
            ->sortBy('name')
            ->values();
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

    /** @return HasMany<QuestionSimilarityMatch, $this> */
    public function similarityMatchesAsLow(): HasMany
    {
        return $this->hasMany(QuestionSimilarityMatch::class, 'question_id_low');
    }

    /** @return HasMany<QuestionSimilarityMatch, $this> */
    public function similarityMatchesAsHigh(): HasMany
    {
        return $this->hasMany(QuestionSimilarityMatch::class, 'question_id_high');
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

    /** @return BelongsTo<QuestionImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(QuestionImportBatch::class, 'import_batch_id');
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

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function instructorSlot1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_1_id');
    }

    /** @return BelongsTo<User, $this> */
    public function instructorSlot2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_2_id');
    }

    /** @return HasMany<QuestionInstructorReview, $this> */
    public function instructorReviews(): HasMany
    {
        return $this->hasMany(QuestionInstructorReview::class);
    }

    /**
     * Publication state for the admin list "Trạng thái" column.
     * Working-copy pipeline (draft / in_review / …) belongs to editorialSubmissionLabel().
     */
    public function publicationStateLabel(): string
    {
        return match ($this->status) {
            QuestionStatus::Retired => 'Ngừng dùng',
            QuestionStatus::Private => 'Riêng tư (exam)',
            QuestionStatus::Published => 'Đã xuất bản',
            default => ((int) $this->published_version > 0)
                ? 'Đã xuất bản'
                : 'Chưa xuất bản',
        };
    }

    public function hasEditorialSubmission(): bool
    {
        return in_array($this->status, [
            QuestionStatus::InReview,
            QuestionStatus::PendingPublish,
            QuestionStatus::Rejected,
        ], true);
    }

    /**
     * Whether the editor has a submitted (or rejected) working copy, and where it sits.
     * Draft / unpublished copies are "Không có bản gửi" no matter how many times they were saved.
     */
    public function editorialSubmissionLabel(): string
    {
        $isUpdate = (int) $this->published_version > 0;

        return match ($this->status) {
            QuestionStatus::InReview => $isUpdate ? 'Đang duyệt cập nhật' : 'Đang chờ duyệt',
            QuestionStatus::PendingPublish => $isUpdate ? 'Cập nhật đủ phiếu' : 'Đủ phiếu · chờ xuất bản',
            QuestionStatus::Rejected => $this->isInstructorRejection()
                ? 'Giảng viên từ chối'
                : ($this->isPublisherRejection() ? 'Admin trả về biên tập' : 'Bản gửi bị từ chối'),
            default => 'Không có bản gửi',
        };
    }

    /** Layer 1: một giảng viên từ chối chuyên môn — câu chưa tới admin. */
    public function isInstructorRejection(): bool
    {
        if ($this->status !== QuestionStatus::Rejected) {
            return false;
        }

        $role = Role::tryFrom((string) $this->rejected_by_role);
        if ($role === Role::Instructor) {
            return true;
        }
        if (in_array($role, [Role::Admin, Role::SuperAdmin], true)) {
            return false;
        }

        return collect($this->instructorReviewFlags())
            ->contains(fn (array $flag): bool => ($flag['decision'] ?? null) === InstructorReviewDecision::Rejected->value);
    }

    /** Layer 2: admin trả về vì lý do vận hành sau khi đủ 2 phiếu GV. */
    public function isPublisherRejection(): bool
    {
        if ($this->status !== QuestionStatus::Rejected || $this->isInstructorRejection()) {
            return false;
        }

        return in_array(Role::tryFrom((string) $this->rejected_by_role), [Role::Admin, Role::SuperAdmin], true);
    }

    public function rejectorDisplayName(): ?string
    {
        if ($this->isInstructorRejection()) {
            foreach ($this->instructorReviewFlags() as $flag) {
                if (($flag['decision'] ?? null) === InstructorReviewDecision::Rejected->value
                    && filled($flag['instructor_name'])) {
                    return $flag['instructor_name'];
                }
            }

            return $this->instructor?->name;
        }

        return $this->publisher?->name ?? $this->reviewer?->name;
    }

    /**
     * Two medical-review flags for admin/teach lists.
     *
     * @return list<array{slot: int, decision: string|null, color: string, instructor_name: string|null, label: string}>
     */
    public function instructorReviewFlags(): array
    {
        return [
            $this->instructorReviewFlag(1, $this->instructor_1_decision, $this->instructorSlot1?->name),
            $this->instructorReviewFlag(2, $this->instructor_2_decision, $this->instructorSlot2?->name),
        ];
    }

    /**
     * @return array{slot: int, decision: string|null, color: string, instructor_name: string|null, label: string}
     */
    private function instructorReviewFlag(int $slot, ?string $decision, ?string $name): array
    {
        $color = match ($decision) {
            InstructorReviewDecision::Approved->value => 'green',
            InstructorReviewDecision::Rejected->value => 'red',
            default => 'white',
        };

        $who = $name ?: 'Giảng viên '.$slot;
        $label = match ($decision) {
            InstructorReviewDecision::Approved->value => $who.' đã chấp nhận',
            InstructorReviewDecision::Rejected->value => $who.' đã từ chối',
            default => 'Giảng viên '.$slot.' chưa duyệt / chờ duyệt',
        };

        return [
            'slot' => $slot,
            'decision' => $decision,
            'color' => $color,
            'instructor_name' => $name,
            'label' => $label,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_id');
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

    /** @return HasOne<QuestionReviewRequest, $this> */
    public function latestRejectedReviewRequest(): HasOne
    {
        return $this->hasOne(QuestionReviewRequest::class)
            ->where('status', QuestionReviewStatus::Rejected->value)
            ->latestOfMany('reviewed_at');
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
        // Always start from author order so the seeded shuffle is stable across loads.
        $options = ($this->relationLoaded('options')
            ? $this->options->sortBy(fn (QuestionOption $option): int => (int) $option->order)->values()
            : $this->options()->orderBy('order')->get()
        )->all();

        $seed = hexdec(substr(hash('sha256', $sessionKey.'|'.$this->getKey()), 0, 8));
        for ($i = count($options) - 1; $i > 0; $i--) {
            $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
            $j = $seed % ($i + 1);
            [$options[$i], $options[$j]] = [$options[$j], $options[$i]];
        }

        $labels = range('A', 'Z');

        return collect($options)->values()->map(function (QuestionOption $option, int $index) use ($labels) {
            // Display letter only — identity for grading remains option id.
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
        $source = $this;
        if (ServePublishedQuestion::needsOverlay($this)) {
            $source = ServePublishedQuestion::overlay(
                static::query()->with([
                    'lessons:id',
                    'tags:id',
                    'options',
                ])->find($this->getKey()) ?? $this,
            );
        }

        $plainStem = strip_tags(html_entity_decode(
            (string) $source->stem,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        ));
        $plainStem = trim(preg_replace('/\s+/u', ' ', $plainStem) ?? $plainStem);

        $lessonIds = ($source->relationLoaded('lessons')
            ? $source->lessons
            : $source->lessons()->get())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $coreClinicalTopicIds = app(QuestionFilterBuilder::class)
            ->inferredCoreClinicalTopicIds($lessonIds);

        $tagIds = ($source->relationLoaded('tags') ? $source->tags : $source->tags()->get())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return [
            'id' => $this->getKey(),
            'stem' => $plainStem,
            'difficulty' => $source->difficulty->value,
            'core_clinical_topic_ids' => $coreClinicalTopicIds,
            'lesson_ids' => $lessonIds,
            'tag_ids' => $tagIds,
            'is_free' => ServePublishedQuestion::publishedIsFree($this),
        ];
    }

    /** Only live (or last published snapshot) questions are searchable. */
    public function shouldBeSearchable(): bool
    {
        return ServePublishedQuestion::isAvailable($this);
    }

    public function isAvailableInQbank(): bool
    {
        return ServePublishedQuestion::isAvailable($this);
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
