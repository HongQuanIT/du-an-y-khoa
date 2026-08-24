<?php

declare(strict_types=1);

namespace Modules\Notification\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Notification\Models\UserNotification;
use Modules\Notification\Support\NotificationCatalog;

/** Realtime push to private-user.{id} when an in-app notification is created. */
final class UserNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public UserNotification $notification) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $n = $this->notification;

        return [
            'id' => $n->id,
            'type' => $n->type,
            'category' => $n->category,
            'title' => $n->title,
            'body' => $n->body,
            'action_url' => $n->action_url,
            'icon' => NotificationCatalog::icon($n->type),
            'created_at' => $n->created_at?->toIso8601String(),
            'read_at' => null,
        ];
    }
}
