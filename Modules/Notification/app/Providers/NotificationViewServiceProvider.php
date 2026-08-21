<?php

declare(strict_types=1);

namespace Modules\Notification\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Notification\View\Composers\HeaderNotificationsComposer;

final class NotificationViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer([
            'components.layouts.app',
            'components.layouts.admin',
            'components.layouts.teach',
            'notification::partials.bell',
        ], HeaderNotificationsComposer::class);
    }
}
