<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use App\Models\User;
use App\Support\Enums\Role;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Events\LiveSessionStarted;
use Modules\Notification\Actions\CreateUserNotificationAction;

/** Notify learners system-wide when an instructor starts a live session. */
final class NotifyLiveSessionStarted
{
    public function __construct(private readonly CreateUserNotificationAction $notify) {}

    public function handle(LiveSessionStarted $event): void
    {
        $session = $event->session->loadMissing('classroom.members');
        $classroom = $session->classroom;
        if ($classroom === null) {
            return;
        }

        $activeMemberIds = $classroom->members()
            ->where('status', MemberStatus::Active->value)
            ->where('user_id', '!=', $classroom->host_user_id)
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $liveUrl = route('classroom.live', [
            'classroom' => $classroom,
            'liveSession' => $session,
        ]);
        $classroomUrl = route('classroom.show', $classroom);

        foreach (User::query()->role(Role::Student->value)->cursor() as $user) {
            $isActiveMember = in_array((int) $user->getKey(), $activeMemberIds, true);

            $this->notify->handle(
                user: $user,
                type: 'live.started',
                title: 'Lớp đang live',
                body: sprintf('“%s” đã bắt đầu. Vào phòng ngay.', $classroom->title),
                data: [
                    'classroom_id' => $classroom->getKey(),
                    'session_id' => $session->getKey(),
                ],
                actionUrl: $isActiveMember ? $liveUrl : $classroomUrl,
            );
        }
    }
}
