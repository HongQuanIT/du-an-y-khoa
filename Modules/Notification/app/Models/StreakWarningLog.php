<?php

declare(strict_types=1);

namespace Modules\Notification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $warning_date
 * @property int $streak_count
 */
class StreakWarningLog extends Model
{
    protected $fillable = [
        'user_id',
        'warning_date',
        'streak_count',
        'sent_at',
    ];

    protected $casts = [
        'warning_date' => 'date',
        'sent_at' => 'datetime',
        'streak_count' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
