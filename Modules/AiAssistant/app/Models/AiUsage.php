<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily quota ledger — one row per user/day (reconciliation source of truth).
 *
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $count
 */
class AiUsage extends Model
{
    protected $table = 'ai_usage';

    protected $fillable = [
        'user_id',
        'date',
        'count',
    ];

    protected $casts = [
        'date' => 'date',
        'count' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
