<?php

declare(strict_types=1);

namespace Modules\Classroom\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionMessage;
use Modules\Classroom\Support\LiveUserPresenter;

final class LiveMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public LiveSession $session,
        public LiveSessionMessage $message,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('live-session.'.$this->session->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('user');

        return [
            'message' => [
                'id' => $this->message->getKey(),
                'body' => $this->message->body,
                'type' => $this->message->type->value,
                'is_pinned' => $this->message->is_pinned,
                'created_at' => $this->message->created_at?->toIso8601String(),
                'user' => LiveUserPresenter::toArray($this->message->user),
            ],
        ];
    }
}
