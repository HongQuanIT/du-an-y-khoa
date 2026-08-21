<?php

declare(strict_types=1);

namespace Modules\Notification\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Notification\Events\UserNotificationCreated;
use Modules\Notification\Models\UserNotification;
use Modules\Notification\Support\NotificationCatalog;

final class CreateUserNotificationAction
{
    use AsAction;

    public function handle(
        User $user,
        string $type,
        string $title,
        string $body,
        ?array $data = null,
        ?string $actionUrl = null,
        bool $broadcast = true,
    ): ?UserNotification {
        if (! $this->allowsInApp($user, $type)) {
            return null;
        }

        $notification = UserNotification::query()->create([
            'user_id' => $user->getKey(),
            'type' => $type,
            'category' => NotificationCatalog::category($type),
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'action_url' => $actionUrl,
        ]);

        if ($broadcast) {
            event(new UserNotificationCreated($notification));
        }

        return $notification;
    }

    private function allowsInApp(User $user, string $type): bool
    {
        if (NotificationCatalog::bypassesPreferences($type)) {
            return true;
        }

        $prefKey = NotificationCatalog::preferenceKey($type);
        if ($prefKey === null) {
            return true;
        }

        $prefs = array_merge(
            NotificationCatalog::defaultPreferences(),
            is_array($user->notification_prefs) ? $user->notification_prefs : [],
        );

        return (bool) ($prefs[$prefKey] ?? true);
    }
}
