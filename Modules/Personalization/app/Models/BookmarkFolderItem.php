<?php

declare(strict_types=1);

namespace Modules\Personalization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $folder_id
 * @property string $question_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class BookmarkFolderItem extends Model
{
    protected $fillable = [
        'folder_id',
        'question_id',
    ];

    /** @return BelongsTo<BookmarkFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(BookmarkFolder::class, 'folder_id');
    }
}
