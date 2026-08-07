<?php

declare(strict_types=1);

namespace Modules\Classroom\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Classroom\Models\LiveSession;

final class LiveReactionSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public LiveSession $session,
        public User $user,
        public string $type,
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
        return 'reaction.sent';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'user' => [
                'id' => $this->user->getKey(),
                'name' => $this->user->name,
            ],
        ];
    }
}
