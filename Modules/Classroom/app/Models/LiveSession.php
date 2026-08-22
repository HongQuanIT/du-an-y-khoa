<?php

declare(strict_types=1);

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Classroom\Enums\LiveSessionStatus;

/**
 * @property int $id
 * @property string $uuid
 * @property int $classroom_id
 * @property string $title
 * @property LiveSessionStatus $status
 * @property string|null $livekit_room_name
 */
class LiveSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'classroom_id',
        'title',
        'scheduled_at',
        'started_at',
        'ended_at',
        'status',
        'livekit_room_name',
        'linked_exam_id',
        'question_set',
        'current_question_index',
        'chat_muted',
        'show_answer',
        'revealed_option_ids',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'status' => LiveSessionStatus::class,
        'question_set' => 'array',
        'current_question_index' => 'integer',
        'chat_muted' => 'boolean',
        'show_answer' => 'boolean',
        'revealed_option_ids' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (LiveSession $session): void {
            if ($session->uuid === null || $session->uuid === '') {
                $session->uuid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Classroom, $this> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** @return HasMany<LiveSessionMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(LiveSessionMessage::class)->orderBy('created_at');
    }

    /** @return HasMany<LiveRecording, $this> */
    public function recordings(): HasMany
    {
        return $this->hasMany(LiveRecording::class);
    }

    /** @return HasMany<LiveSessionHand, $this> */
    public function hands(): HasMany
    {
        return $this->hasMany(LiveSessionHand::class);
    }

    /** @return list<string> */
    public function questionIds(): array
    {
        $set = $this->question_set ?? [];

        return array_values(array_map('strval', $set['question_ids'] ?? []));
    }

    public function hasQuestionSet(): bool
    {
        return $this->questionIds() !== [];
    }

    /** @return list<int> */
    public function revealedOptionIds(): array
    {
        return array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $this->revealed_option_ids ?? [],
        )));
    }

    public function isLive(): bool
    {
        return $this->status->isLive();
    }

    public function allowsChatSend(): bool
    {
        return $this->status->allowsChatSend();
    }
}
