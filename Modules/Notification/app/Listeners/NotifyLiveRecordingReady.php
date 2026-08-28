<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use App\Models\User;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Events\LiveRecordingReady;
use Modules\Notification\Actions\CreateUserNotificationAction;

/** Keep legacy recording notifications pointed to the class page. */
final class NotifyLiveRecordingReady
{
    public function __construct(private readonly CreateUserNotificationAction $notify) {}

    public function handle(LiveRecordingReady $event): void
    {
        $session = $event->session->loadMissing('classroom');
        $classroom = $session->classroom;
        if ($classroom === null) {
            return;
        }

        $userIds = $classroom->members()
            ->where('status', MemberStatus::Active->value)
            ->pluck('user_id')
            ->push($classroom->host_user_id)
            ->unique()
            ->filter();

        $hostUrl = route('teach.classes.show', $classroom);

        foreach (User::query()->whereIn('id', $userIds)->cursor() as $user) {
            $isHost = (int) $user->getKey() === (int) $classroom->host_user_id;

            $this->notify->handle(
                user: $user,
                type: 'recording.ready',
                title: 'Bản ghi sẵn sàng',
                body: sprintf('Xem lại buổi “%s”.', $classroom->title),
                data: [
                    'classroom_id' => $classroom->getKey(),
                    'session_id' => $session->getKey(),
                    'recording_id' => $event->recording->getKey(),
                ],
                actionUrl: $hostUrl,
            );
        }
    }
}
