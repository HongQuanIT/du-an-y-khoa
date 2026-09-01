<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use Modules\Classroom\Events\LiveSessionEnded;
use Modules\Notification\Models\UserNotification;

/** Auto-dismiss / mark as read live session notifications when the host ends the live session. */
final class DismissLiveSessionNotifications
{
    public function handle(LiveSessionEnded $event): void
    {
        $sessionId = (int) $event->session->getKey();

        UserNotification::query()
            ->whereNull('read_at')
            ->whereIn('type', ['live.started', 'live.upcoming'])
            ->where(function ($query) use ($sessionId) {
                $query->where('data->session_id', $sessionId)
                    ->orWhere('data->session_id', (string) $sessionId);
            })
            ->update([
                'read_at' => now(),
            ]);
    }
}
