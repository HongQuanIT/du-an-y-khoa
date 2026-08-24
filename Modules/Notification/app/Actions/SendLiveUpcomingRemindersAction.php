<?php

declare(strict_types=1);

namespace Modules\Notification\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\LiveSession;
use Modules\Notification\Models\LiveUpcomingReminderLog;

/** Notify classroom members before a scheduled live session starts. */
final class SendLiveUpcomingRemindersAction
{
    use AsAction;

    public function handle(?Carbon $now = null): int
    {
        $now = ($now ?? Carbon::now())->copy();
        $leadMinutes = max(1, (int) config('notification.live_upcoming.lead_minutes', 30));
        $windowEnd = $now->copy()->addMinutes($leadMinutes);

        $sessions = LiveSession::query()
            ->with('classroom')
            ->where('status', LiveSessionStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', $now)
            ->where('scheduled_at', '<=', $windowEnd)
            ->get();

        $sent = 0;

        foreach ($sessions as $session) {
            $classroom = $session->classroom;
            if ($classroom === null) {
                continue;
            }

            $memberIds = $classroom->members()
                ->where('status', MemberStatus::Active->value)
                ->pluck('user_id');

            if ($memberIds->isEmpty()) {
                continue;
            }

            $already = LiveUpcomingReminderLog::query()
                ->where('live_session_id', $session->getKey())
                ->whereIn('user_id', $memberIds)
                ->pluck('user_id')
                ->all();

            $pendingIds = $memberIds->diff($already);
            if ($pendingIds->isEmpty()) {
                continue;
            }

            $startsAt = $session->scheduled_at?->timezone(config('app.timezone'));
            $secondsLeft = max(60, (int) ($session->scheduled_at->getTimestamp() - $now->getTimestamp()));
            $minutesLeft = (int) ceil($secondsLeft / 60);
            foreach (User::query()->whereIn('id', $pendingIds)->cursor() as $user) {
                $isHost = (int) $user->getKey() === (int) $classroom->host_user_id;
                $actionUrl = $isHost
                    ? route('teach.classes.sessions.studio', [
                        'classroom' => $classroom,
                        'liveSession' => $session,
                    ])
                    : route('classroom.live', [
                        'classroom' => $classroom,
                        'liveSession' => $session,
                    ]);

                $notification = CreateUserNotificationAction::run(
                    user: $user,
                    type: 'live.upcoming',
                    title: 'Live sắp bắt đầu',
                    body: sprintf(
                        '“%s” bắt đầu lúc %s (còn ~%d phút).',
                        $classroom->title,
                        $startsAt?->format('H:i') ?? '—',
                        $minutesLeft,
                    ),
                    data: [
                        'classroom_id' => $classroom->getKey(),
                        'session_id' => $session->getKey(),
                        'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                        'minutes_left' => $minutesLeft,
                    ],
                    actionUrl: $actionUrl,
                );

                // Dedup even when prefs skip in-app, to avoid retry spam.
                LiveUpcomingReminderLog::query()->create([
                    'live_session_id' => $session->getKey(),
                    'user_id' => $user->getKey(),
                    'sent_at' => $now,
                ]);

                if ($notification !== null) {
                    $sent++;
                }
            }
        }

        return $sent;
    }
}
