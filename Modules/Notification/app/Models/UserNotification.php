<?php

declare(strict_types=1);

namespace Modules\Notification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Notification\Support\NotificationCatalog;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $category
 * @property string $title
 * @property string $body
 * @property array<string, mixed>|null $data
 * @property string|null $action_url
 * @property Carbon|null $read_at
 */
class UserNotification extends Model
{
    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'category',
        'title',
        'body',
        'data',
        'action_url',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => Carbon::now()])->save();
        }
    }

    public function icon(): string
    {
        return NotificationCatalog::icon($this->type);
    }

    public function categoryLabel(): string
    {
        return NotificationCatalog::categoryLabel($this->category);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
