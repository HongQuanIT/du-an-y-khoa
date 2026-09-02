<?php

declare(strict_types=1);

namespace Modules\Notification\Mail;

use App\Models\User;
use App\Support\Queue\Concerns\HasQueueDisplayName;
use App\Support\Queue\QueueName;
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
    use HasQueueDisplayName;
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, StudyPlanTask>  $tasks
     */
    public function __construct(
        public User $user,
        public Collection $tasks,
        public Carbon $reminderDate,
    ) {
        $this->onQueue(QueueName::Mail->value);
    }

    public function displayName(): string
    {
        return sprintf(
            'mail:study-plan-reminder:user-%d:tasks-%d:date-%s',
            $this->user->getKey(),
            $this->tasks->count(),
            $this->reminderDate->toDateString(),
        );
    }

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

    /** @return array<int, string> */
    public function tags(): array
    {
        return $this->featureTags(
            'mail',
            'study-plan-reminder',
            'user:'.$this->user->getKey(),
            'date:'.$this->reminderDate->toDateString(),
        );
    }
}
