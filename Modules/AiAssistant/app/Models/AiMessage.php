<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $thread_id
 * @property int $user_id
 * @property string $role
 * @property string $status
 * @property string|null $preset
 * @property string|null $content
 * @property array<int, array<string, mixed>>|null $citations
 * @property int|null $tokens_in
 * @property int|null $tokens_out
 * @property string|null $feedback_vote
 */
class AiMessage extends Model
{
    use HasUuids;

    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    public const STATUS_PENDING = 'pending';
    public const STATUS_STREAMING = 'streaming';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_STOPPED = 'stopped';

    protected $table = 'ai_messages';

    protected $fillable = [
        'thread_id',
        'user_id',
        'role',
        'status',
        'preset',
        'content',
        'citations',
        'tokens_in',
        'tokens_out',
        'feedback_vote',
    ];

    protected $casts = [
        'citations' => 'array',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
    ];

    /** @return BelongsTo<AiThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(AiThread::class, 'thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return [
            'id' => $this->getKey(),
            'role' => $this->role,
            'status' => $this->status,
            'content' => (string) $this->content,
            'citations' => $this->citations ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
