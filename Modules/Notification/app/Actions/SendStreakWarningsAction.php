<?php

declare(strict_types=1);

namespace Modules\Notification\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use Illuminate\Support\Carbon;
use Modules\Notification\Models\StreakWarningLog;
use Modules\Notification\Support\StudyStreakCalculator;

/** Evening warning for learners who have a streak but have not studied today. */
final class SendStreakWarningsAction
{
    use AsAction;

    public function __construct(private readonly StudyStreakCalculator $streaks) {}

    public function handle(?Carbon $now = null): int
    {
        $now = ($now ?? Carbon::now())->copy();
        $today = $now->copy()->startOfDay();
        $warnAfterHour = (int) config('notification.streak.warn_after_hour', 18);
        $minStreak = max(1, (int) config('notification.streak.min_streak_to_warn', 1));

        if ((int) $now->format('G') < $warnAfterHour) {
            return 0;
        }

        $sent = 0;

        $userIds = User::role(Role::Student->value)->pluck('id');

        foreach ($userIds->chunk(200) as $chunk) {
            $users = User::query()->whereIn('id', $chunk)->get();

            foreach ($users as $user) {
                if ($this->alreadyWarned($user, $today)) {
                    continue;
                }

                if ($this->streaks->metGoalOn($user, $today)) {
                    continue;
                }

                $streak = $this->streaks->currentStreak($user, $today);
                if ($streak < $minStreak) {
                    continue;
                }

                $notification = CreateUserNotificationAction::run(
                    user: $user,
                    type: 'streak.warning',
                    title: 'Streak sắp mất',
                    body: sprintf(
                        'Học ngay để giữ chuỗi %d ngày. Mục tiêu hôm nay chưa hoàn thành.',
                        $streak,
                    ),
                    data: [
                        'streak_count' => $streak,
                        'warning_date' => $today->toDateString(),
                    ],
                    actionUrl: route('dashboard'),
                );

                StreakWarningLog::query()->create([
                    'user_id' => $user->getKey(),
                    'warning_date' => $today->toDateString(),
                    'streak_count' => $streak,
                    'sent_at' => $now,
                ]);

                if ($notification !== null) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    private function alreadyWarned(User $user, Carbon $today): bool
    {
        return StreakWarningLog::query()
            ->where('user_id', $user->getKey())
            ->whereDate('warning_date', $today)
            ->exists();
    }
}
