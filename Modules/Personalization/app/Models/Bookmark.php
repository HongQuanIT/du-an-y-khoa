<?php

declare(strict_types=1);

namespace Modules\Personalization\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-owned saved item. The stable type alias keeps database values
 * independent from PHP namespaces.
 */
final class Bookmark extends Model
{
    public const TYPE_QUESTION = 'question';

    protected $fillable = [
        'user_id',
        'bookmarkable_type',
        'bookmarkable_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hasQuestion(int $userId, string $questionId): bool
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('bookmarkable_type', self::TYPE_QUESTION)
            ->where('bookmarkable_id', $questionId)
            ->exists();
    }

    /** @return array<int, string> */
    public static function questionIdsForUser(int $userId): array
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('bookmarkable_type', self::TYPE_QUESTION)
            ->pluck('bookmarkable_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }
}
