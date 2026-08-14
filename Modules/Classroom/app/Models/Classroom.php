<?php

declare(strict_types=1);

namespace Modules\Classroom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Enums\RecordingStatus;

/**
 * Community live-review classroom (srs/modules/44).
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string|null $description
 * @property int $host_user_id
 * @property ClassroomPurpose $purpose
 * @property ClassroomVisibility $visibility
 * @property string|null $join_code
 * @property ClassroomStatus $status
 * @property int|null $max_members
 */
class Classroom extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'host_user_id',
        'purpose',
        'visibility',
        'join_code',
        'status',
        'max_members',
        'cover_media_id',
        'meta',
    ];

    protected $casts = [
        'purpose' => ClassroomPurpose::class,
        'visibility' => ClassroomVisibility::class,
        'status' => ClassroomStatus::class,
        'max_members' => 'integer',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom): void {
            if ($classroom->uuid === null || $classroom->uuid === '') {
                $classroom->uuid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /** @return HasMany<ClassroomMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ClassroomMember::class);
    }

    /** @return HasMany<ClassroomMember, $this> */
    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', MemberStatus::Active->value);
    }

    /** @return HasMany<LiveSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(LiveSession::class)->orderByDesc('scheduled_at');
    }

    /** Child route binding for `{liveSession}` under `{classroom}`. */
    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    /** @return HasOne<LiveSession, $this> */
    public function liveSession(): HasOne
    {
        return $this->hasOne(LiveSession::class)
            ->where('status', LiveSessionStatus::Live->value)
            ->latestOfMany();
    }

    /** Nearest future scheduled session. */
    /** @return HasOne<LiveSession, $this> */
    public function upcomingSession(): HasOne
    {
        return $this->hasOne(LiveSession::class)
            ->where('status', LiveSessionStatus::Scheduled->value)
            ->where('scheduled_at', '>', now())
            ->oldest('scheduled_at');
    }

    /** Latest ended session with a ready VOD recording. */
    /** @return HasOne<LiveSession, $this> */
    public function replaySession(): HasOne
    {
        return $this->hasOne(LiveSession::class)
            ->where('status', LiveSessionStatus::Ended->value)
            ->whereHas('recordings', fn ($query) => $query->where('status', RecordingStatus::Ready->value))
            ->latest('ended_at');
    }

    public function catalogCoverUrl(): ?string
    {
        $path = $this->meta['cover_path'] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function coverInitial(): string
    {
        return strtoupper(substr($this->title, 0, 1));
    }

    public function isLiveNow(): bool
    {
        return $this->relationLoaded('liveSession')
            ? $this->liveSession !== null
            : $this->liveSession()->exists();
    }

    public function memberFor(User $user): ?ClassroomMember
    {
        return $this->members()->where('user_id', $user->getKey())->first();
    }

    public function isActiveMember(User $user): bool
    {
        $member = $this->memberFor($user);

        return $member !== null && $member->status === MemberStatus::Active;
    }

    public function roleFor(User $user): ?MemberRole
    {
        return $this->memberFor($user)?->role_in_class;
    }

    public function isHostOrCohost(User $user): bool
    {
        $role = $this->roleFor($user);

        return $role !== null && $role->canModerate();
    }
}
