<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SupportMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SupportMessage $message) {}

    public function broadcastOn(): array
    {
        // Presence: typing whispers need channel membership.
        // Private support-admin: sidebar badge + queue list for staff.
        return [
            new PresenceChannel('support-conversation.'.$this->message->support_conversation_id),
            new PrivateChannel('support-admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $conversation = $this->message->relationLoaded('conversation')
            ? $this->message->conversation
            : SupportConversation::query()->find($this->message->support_conversation_id);

        return [
            'message' => [
                'id' => $this->message->id,
                'sender_type' => $this->message->sender_type,
                'sender_id' => $this->message->sender_id,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
            'conversation_id' => $this->message->support_conversation_id,
            'status' => $conversation?->status,
            'needs_reply' => $conversation?->needsAdminReply() ?? false,
        ];
    }
}
