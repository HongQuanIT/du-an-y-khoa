<?php

declare(strict_types=1);

namespace Modules\Notification\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\StudyPlan\Models\StudyPlanTask;

final class StudyPlanReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, StudyPlanTask>  $tasks
     */
    public function __construct(
        public User $user,
        public Collection $tasks,
        public Carbon $reminderDate,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->tasks->count();

        return new Envelope(
            subject: sprintf('Nhắc kế hoạch học — %d nhiệm vụ đang chờ', $count),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'notification::emails.study-plan-reminder',
        );
    }
}
