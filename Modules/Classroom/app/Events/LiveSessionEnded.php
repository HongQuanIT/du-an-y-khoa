<?php

declare(strict_types=1);

namespace Modules\Classroom\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Classroom\Models\LiveSession;

final class LiveSessionEnded implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public LiveSession $session) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('live-session.'.$this->session->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.ended';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->uuid,
            'ended_at' => $this->session->ended_at?->toIso8601String(),
        ];
    }
}
