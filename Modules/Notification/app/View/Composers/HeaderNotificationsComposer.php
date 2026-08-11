<?php

declare(strict_types=1);

namespace Modules\Notification\View\Composers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Modules\Notification\Models\UserNotification;

final class HeaderNotificationsComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();
        if ($user === null) {
            $view->with([
                'headerNotifications' => collect(),
                'headerUnreadCount' => 0,
            ]);

            return;
        }

        $baseQuery = UserNotification::query()->where('user_id', $user->getKey());

        $view->with([
            'headerNotifications' => (clone $baseQuery)->latest()->limit(8)->get(),
            'headerUnreadCount' => (clone $baseQuery)->whereNull('read_at')->count(),
        ]);
    }
}
