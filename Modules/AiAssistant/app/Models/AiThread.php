<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $title
 * @property string|null $context_type
 * @property string|null $context_id
 * @property string|null $context_source
 * @property string|null $session_id
 * @property string|null $preset
 */
class AiThread extends Model
{
    use HasUuids;

    protected $table = 'ai_threads';

    protected $fillable = [
        'user_id',
        'title',
        'context_type',
        'context_id',
        'context_source',
        'session_id',
        'preset',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AiMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'thread_id')->orderBy('created_at');
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->getKey();
    }
}
