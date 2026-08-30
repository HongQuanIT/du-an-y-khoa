<?php

namespace Modules\Notification\Providers;

use App\Events\ContactInquirySubmitted;
use App\Events\SupportMessageCreated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Classroom\Events\LiveRecordingReady;
use Modules\Classroom\Events\LiveSessionStarted;
use Modules\Notification\Listeners\NotifyContactInquirySubmitted;
use Modules\Notification\Listeners\NotifyLiveRecordingReady;
use Modules\Notification\Listeners\NotifyLiveSessionStarted;
use Modules\Notification\Listeners\NotifySessionCompleted;
use Modules\Notification\Listeners\NotifySupportMessage;
use Modules\QuestionBank\Data\QuestionSessionProgressed;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        QuestionSessionProgressed::class => [
            NotifySessionCompleted::class,
        ],
        LiveSessionStarted::class => [
            NotifyLiveSessionStarted::class,
        ],
        LiveRecordingReady::class => [
            NotifyLiveRecordingReady::class,
        ],
        SupportMessageCreated::class => [
            NotifySupportMessage::class,
        ],
        ContactInquirySubmitted::class => [
            NotifyContactInquirySubmitted::class,
        ],
    ];

    protected static $shouldDiscoverEvents = true;

    protected function configureEmailVerification(): void {}
}
