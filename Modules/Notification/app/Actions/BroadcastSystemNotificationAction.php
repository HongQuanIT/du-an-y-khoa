<?php

declare(strict_types=1);

namespace Modules\Notification\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use Illuminate\Support\Collection;
use Modules\Admin\Support\Auditor;
use Modules\Notification\Jobs\FanOutSystemNotificationJob;

/**
 * Admin broadcast: fan-out system notifications to an audience.
 *
 * audience: all | learners | instructors | staff
 */
final class BroadcastSystemNotificationAction
{
    use AsAction;

    private const CHUNK = 200;

    public function handle(
        User $actor,
        string $title,
        string $body,
        string $audience = 'all',
        string $type = 'system.broadcast',
        ?string $actionUrl = null,
    ): int {
        $userIds = $this->audienceUserIds($audience);
        $total = $userIds->count();

        foreach ($userIds->chunk(self::CHUNK) as $chunk) {
            FanOutSystemNotificationJob::dispatch(
                userIds: $chunk->values()->all(),
                type: $type,
                title: $title,
                body: $body,
                data: [
                    'audience' => $audience,
                    'broadcast_by' => $actor->getKey(),
                ],
                actionUrl: $actionUrl,
            );
        }

        Auditor::record(
            action: 'notification.system_broadcast',
            actor: $actor,
            auditable: null,
            before: null,
            after: [
                'audience' => $audience,
                'type' => $type,
                'title' => $title,
                'recipient_count' => $total,
            ],
        );

        return $total;
    }

    /** @return Collection<int, int> */
    private function audienceUserIds(string $audience): Collection
    {
        $query = User::query()->select('id');

        return match ($audience) {
            'learners' => $query->role(Role::Student->value)->pluck('id'),
            'instructors' => $query->role(Role::Instructor->value)->pluck('id'),
            'staff' => $query->role([
                Role::Admin->value,
                Role::SuperAdmin->value,
                Role::ContentEditor->value,
            ])->pluck('id'),
            default => $query->pluck('id'),
        };
    }
}
