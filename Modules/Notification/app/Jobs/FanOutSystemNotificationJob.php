<?php

declare(strict_types=1);

namespace Modules\Notification\Jobs;

use App\Models\User;
use App\Support\Queue\Concerns\HasQueueDisplayName;
use App\Support\Queue\QueueName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notification\Actions\CreateUserNotificationAction;

final class FanOutSystemNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use HasQueueDisplayName;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public array $userIds,
        public string $type,
        public string $title,
        public string $body,
        public ?array $data = null,
        public ?string $actionUrl = null,
    ) {
        $this->onQueue(QueueName::Notifications->value);
    }

    public function displayName(): string
    {
        $broadcastBy = $this->data['broadcast_by'] ?? 'system';
        $count = count($this->userIds);
        $firstId = $this->userIds[0] ?? 0;
        $lastId = $this->userIds[$count - 1] ?? $firstId;

        return sprintf(
            'notifications:fan-out:%s:by-user-%s:recipients-%d:ids-%d-%d',
            $this->type,
            $broadcastBy,
            $count,
            $firstId,
            $lastId,
        );
    }

    public function handle(CreateUserNotificationAction $create): void
    {
        $users = User::query()->whereIn('id', $this->userIds)->get();

        foreach ($users as $user) {
            $create->handle(
                user: $user,
                type: $this->type,
                title: $this->title,
                body: $this->body,
                data: $this->data,
                actionUrl: $this->actionUrl,
            );
        }
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        $broadcastBy = (string) ($this->data['broadcast_by'] ?? 'system');

        return $this->featureTags(
            'notifications',
            'fan-out',
            'type:'.$this->type,
            'broadcast-by:'.$broadcastBy,
            'count:'.count($this->userIds),
        );
    }
}
