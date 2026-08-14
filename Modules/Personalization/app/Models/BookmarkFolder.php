<?php

declare(strict_types=1);

namespace Modules\Personalization\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class BookmarkFolder extends Model
{
    protected $fillable = [
        'user_id',
        'name',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<BookmarkFolderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BookmarkFolderItem::class, 'folder_id');
    }
}
