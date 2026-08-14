<?php

declare(strict_types=1);

namespace Modules\Personalization\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * A subquery builder scoped to a user's bookmarked question IDs.
     * Use with ->whereIn('id', Bookmark::bookmarkSubquery($userId))
     * to push the filter into the DB instead of fetching IDs into PHP.
     *
     * @return Builder<self>
     */
    public static function bookmarkSubquery(int $userId): Builder
    {
        return self::query()
            ->select('bookmarkable_id')
            ->where('user_id', $userId)
            ->where('bookmarkable_type', self::TYPE_QUESTION);
    }

    /**
     * Efficient single-row EXISTS check — no data fetched, only probes the
     * (user_id, bookmarkable_type, bookmarkable_id) unique index.
     */
    public static function hasQuestion(int $userId, string $questionId): bool
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('bookmarkable_type', self::TYPE_QUESTION)
            ->where('bookmarkable_id', $questionId)
            ->exists();
    }

    /**
     * Returns all bookmarked question IDs for a user.
     * Result is memoized per-request with once() so repeated calls
     * within the same request lifecycle hit the DB only once.
     *
     * @return array<int, string>
     */
    public static function questionIdsForUser(int $userId): array
    {
        return once(static fn (): array => self::query()
            ->where('user_id', $userId)
            ->where('bookmarkable_type', self::TYPE_QUESTION)
            ->pluck('bookmarkable_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all()
        );
    }
}
