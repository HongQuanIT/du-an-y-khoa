<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

final class SupportConversation extends Model
{
    public const CATEGORIES = ['account', 'billing', 'course', 'system', 'other'];
    public const OPEN_STATUSES = ['ai_active', 'waiting_admin', 'admin_active'];

    public const CATEGORY_LABELS = [
        'account' => 'Tài khoản',
        'billing' => 'Thanh toán',
        'course' => 'Khóa học',
        'system' => 'Lỗi hệ thống',
        'other' => 'Khác',
    ];

    protected $fillable = ['user_id', 'assigned_admin_id', 'category', 'status', 'subject', 'last_message_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** @return BelongsTo<User, $this> */
    public function assignedAdmin(): BelongsTo { return $this->belongsTo(User::class, 'assigned_admin_id'); }

    /** @return HasMany<SupportMessage, $this> */
    public function messages(): HasMany { return $this->hasMany(SupportMessage::class); }

    /** @return HasOne<SupportMessage, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    /** @return HasMany<SupportConversationAdminRead, $this> */
    public function adminReads(): HasMany { return $this->hasMany(SupportConversationAdminRead::class); }

    public function isOwnedBy(User $user): bool { return (int) $this->user_id === (int) $user->getKey(); }

    public function needsAdminReply(): bool
    {
        if (! in_array($this->status, ['waiting_admin', 'admin_active'], true)) {
            return false;
        }

        if ($this->status === 'waiting_admin') {
            return true;
        }

        $latest = $this->relationLoaded('latestMessage')
            ? $this->latestMessage
            : $this->latestMessage()->first();

        return $latest?->sender_type === 'user';
    }

    public function isUnreadForAdmin(User $admin): bool
    {
        if (! in_array($this->status, ['waiting_admin', 'admin_active'], true)) {
            return false;
        }

        $latestId = $this->relationLoaded('latestMessage')
            ? (int) ($this->latestMessage?->id ?? 0)
            : (int) ($this->messages()->max('id') ?? 0);

        if ($latestId === 0) {
            return false;
        }

        $read = $this->relationLoaded('adminReads')
            ? $this->adminReads->firstWhere('admin_id', $admin->id)
            : $this->adminReads()->where('admin_id', $admin->id)->first();

        return $read === null || (int) $read->last_seen_message_id < $latestId;
    }

    /**
     * @return array{key: string, label: string, tone: string}
     */
    public function adminWorkflowStatus(): array
    {
        if ($this->status === 'resolved') {
            return ['key' => 'resolved', 'label' => 'Đã đóng', 'tone' => 'muted'];
        }

        if ($this->status === 'ai_active') {
            return ['key' => 'ai_active', 'label' => 'AI đang hỗ trợ', 'tone' => 'info'];
        }

        if ($this->status === 'waiting_admin') {
            return ['key' => 'waiting', 'label' => 'Chờ xử lý', 'tone' => 'warning'];
        }

        if ($this->needsAdminReply()) {
            return ['key' => 'unreplied', 'label' => 'Chưa trả lời', 'tone' => 'warning'];
        }

        return ['key' => 'handling', 'label' => 'Đang xử lý', 'tone' => 'success'];
    }

    public function isHandledByOtherAdmin(User $admin): bool
    {
        return $this->assigned_admin_id !== null
            && (int) $this->assigned_admin_id !== (int) $admin->getKey()
            && $this->status !== 'resolved';
    }

    public function claimByAdmin(User $admin): void
    {
        if ($this->status === 'resolved') {
            return;
        }

        $this->forceFill([
            'assigned_admin_id' => $admin->getKey(),
            'status' => 'admin_active',
        ])->save();
    }

    /** @return array{label: string, tone: string, needs_reply: bool, unread: bool} */
    public function adminListStateFor(User $admin): array
    {
        $unread = $this->isUnreadForAdmin($admin);
        $needsReply = $this->needsAdminReply();
        $workflow = $this->adminWorkflowStatus();

        return [
            'label' => $workflow['label'],
            'tone' => $workflow['tone'],
            'needs_reply' => $needsReply,
            'unread' => $unread,
        ];
    }

    public function markSeenByAdmin(User $admin): void
    {
        $latestMessageId = (int) ($this->messages()->max('id') ?? 0);

        SupportConversationAdminRead::query()->updateOrCreate(
            ['support_conversation_id' => $this->id, 'admin_id' => $admin->id],
            ['last_seen_message_id' => $latestMessageId, 'seen_at' => now()],
        );
    }

    /** @return list<int> */
    public static function pendingAdminAttentionIdsFor(User $admin): array
    {
        return static::query()
            ->whereIn('status', ['waiting_admin', 'admin_active'])
            ->where(function ($query): void {
                $query->where('status', 'waiting_admin')
                    ->orWhereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('support_messages as sm')
                            ->whereColumn('sm.support_conversation_id', 'support_conversations.id')
                            ->where('sm.sender_type', 'user')
                            ->whereRaw('sm.id = (SELECT MAX(id) FROM support_messages WHERE support_conversation_id = support_conversations.id)');
                    });
            })
            ->whereNotExists(function ($sub) use ($admin): void {
                $sub->selectRaw('1')
                    ->from('support_conversation_admin_reads as r')
                    ->whereColumn('r.support_conversation_id', 'support_conversations.id')
                    ->where('r.admin_id', $admin->id)
                    ->whereColumn('r.last_seen_message_id', '>=', DB::raw(
                        '(SELECT COALESCE(MAX(id), 0) FROM support_messages WHERE support_conversation_id = support_conversations.id)'
                    ));
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** Conversations needing admin attention that this admin has not opened since the latest message. */
    public static function pendingAdminAttentionCountFor(User $admin): int
    {
        return count(static::pendingAdminAttentionIdsFor($admin));
    }
}
