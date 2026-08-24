<?php

declare(strict_types=1);

namespace Modules\Notification\Jobs;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notification\Actions\CreateUserNotificationAction;

final class FanOutSystemNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
    ) {}

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
}
