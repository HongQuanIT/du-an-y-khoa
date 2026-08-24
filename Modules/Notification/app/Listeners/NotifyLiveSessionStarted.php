<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use App\Models\User;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Events\LiveSessionStarted;
use Modules\Notification\Actions\CreateUserNotificationAction;

/** Notify active classroom members (except host) when a live session starts. */
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

        $memberIds = $classroom->members()
            ->where('status', MemberStatus::Active->value)
            ->where('user_id', '!=', $classroom->host_user_id)
            ->pluck('user_id');

        $url = route('classroom.live', [
            'classroom' => $classroom,
            'liveSession' => $session,
        ]);

        foreach (User::query()->whereIn('id', $memberIds)->cursor() as $user) {
            $this->notify->handle(
                user: $user,
                type: 'live.started',
                title: 'Lớp đang live',
                body: sprintf('“%s” đã bắt đầu. Vào phòng ngay.', $classroom->title),
                data: [
                    'classroom_id' => $classroom->getKey(),
                    'session_id' => $session->getKey(),
                ],
                actionUrl: $url,
            );
        }
    }
}
