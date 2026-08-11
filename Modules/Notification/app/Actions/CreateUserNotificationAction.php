<?php

declare(strict_types=1);

namespace Modules\Notification\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Notification\Models\UserNotification;

final class CreateUserNotificationAction
{
    use AsAction;

    public function handle(
        User $user,
        string $type,
        string $title,
        string $body,
        ?array $data = null,
    ): ?UserNotification {
        $prefs = $user->notification_prefs ?? [];
        if ($type === 'session.completed' && ! ($prefs['push_reminders'] ?? true)) {
            return null;
        }

        return UserNotification::query()->create([
            'user_id' => $user->getKey(),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
