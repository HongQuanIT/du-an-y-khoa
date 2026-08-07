<?php

declare(strict_types=1);

namespace Modules\Classroom\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionHand;

final class LiveHandsUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<array{id: int|string, user: array{id: int|null, name: string|null}, raised_at: string|null}>  $hands
     * @param  'raised'|'lowered'|'dismissed'|'updated'  $action
     */
    public function __construct(
        public LiveSession $session,
        public array $hands,
        public string $action = 'updated',
        public ?int $actorUserId = null,
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
        return 'hands.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'hands' => $this->hands,
            'action' => $this->action,
            'actor_user_id' => $this->actorUserId,
        ];
    }

    /**
     * @return list<array{id: int|string, user: array{id: int|null, name: string|null}, raised_at: string|null}>
     */
    public static function serializeActiveHands(LiveSession $session): array
    {
        return $session->hands()
            ->whereNull('acknowledged_at')
            ->with('user')
            ->orderBy('raised_at')
            ->get()
            ->map(fn (LiveSessionHand $h): array => [
                'id' => $h->getKey(),
                'user' => [
                    'id' => $h->user?->getKey(),
                    'name' => $h->user?->name,
                ],
                'raised_at' => $h->raised_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
