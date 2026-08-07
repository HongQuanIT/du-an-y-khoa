<?php

declare(strict_types=1);

namespace Modules\Classroom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;

/**
 * @property int $id
 * @property int $classroom_id
 * @property int $user_id
 * @property MemberRole $role_in_class
 * @property MemberStatus $status
 */
class ClassroomMember extends Model
{
    protected $fillable = [
        'classroom_id',
        'user_id',
        'role_in_class',
        'status',
        'joined_at',
    ];

    protected $casts = [
        'role_in_class' => MemberRole::class,
        'status' => MemberStatus::class,
        'joined_at' => 'datetime',
    ];

    /** @return BelongsTo<Classroom, $this> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active;
    }
}
