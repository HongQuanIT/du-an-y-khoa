<?php

declare(strict_types=1);

namespace Modules\Classroom\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Classroom\Models\LiveSession;

/** Domain event after a live session transitions to live (for notifications, etc.). */
final class LiveSessionStarted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public LiveSession $session) {}
}
