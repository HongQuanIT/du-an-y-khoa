<?php

declare(strict_types=1);

namespace Modules\Notification\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Notification\Actions\SendStreakWarningsAction;
use Modules\Notification\Models\StreakWarningLog;
use Modules\Notification\Models\UserNotification;
use Modules\Notification\Support\StudyStreakCalculator;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\Topic;
use Tests\TestCase;

final class StreakWarningTest extends TestCase
{
    use RefreshDatabase;

    private Topic $topic;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config([
            'notification.streak.min_questions_per_day' => 1,
            'notification.streak.min_streak_to_warn' => 1,
            'notification.streak.warn_after_hour' => 18,
        ]);

        $this->topic = Topic::query()->create([
            'name' => 'Streak Topic',
            'slug' => 'streak-topic-test',
            'type' => 'system',
            'order' => 1,
        ]);

        $this->question = Question::query()->create([
            'stem' => 'Câu streak?',
            'explanation' => 'Giải thích',
            'key_info' => [],
            'difficulty' => Difficulty::Easy,
            'status' => QuestionStatus::Published,
            'topic_id' => $this->topic->getKey(),
            'is_free' => true,
        ]);
    }

    public function test_calculator_counts_consecutive_days(): void
    {
        $user = User::factory()->create();
        $this->recordAnswer($user, Carbon::parse('2026-08-19 10:00:00'));
        $this->recordAnswer($user, Carbon::parse('2026-08-20 11:00:00'));

        $streak = app(StudyStreakCalculator::class)
            ->currentStreak($user, Carbon::parse('2026-08-21'));

        $this->assertSame(2, $streak);
    }

    public function test_sends_warning_when_streak_at_risk(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 19:00:00'));

        $user = User::factory()->create([
            'notification_prefs' => ['push_reminders' => true],
        ]);
        $user->assignRole(Role::Student->value);
        $this->recordAnswer($user, Carbon::parse('2026-08-20 09:00:00'));

        $sent = SendStreakWarningsAction::run();

        $this->assertSame(1, $sent);
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $user->getKey())
                ->where('type', 'streak.warning')
                ->exists()
        );
        $this->assertTrue(
            StreakWarningLog::query()
                ->where('user_id', $user->getKey())
                ->whereDate('warning_date', '2026-08-21')
                ->exists()
        );
    }

    public function test_skips_before_warn_hour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00'));

        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);
        $this->recordAnswer($user, Carbon::parse('2026-08-20 09:00:00'));

        $this->assertSame(0, SendStreakWarningsAction::run());
    }

    public function test_skips_when_already_studied_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 19:00:00'));

        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);
        $this->recordAnswer($user, Carbon::parse('2026-08-20 09:00:00'));
        $this->recordAnswer($user, Carbon::parse('2026-08-21 12:00:00'));

        $this->assertSame(0, SendStreakWarningsAction::run());
    }

    public function test_does_not_duplicate_same_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 19:00:00'));

        $user = User::factory()->create([
            'notification_prefs' => ['push_reminders' => true],
        ]);
        $user->assignRole(Role::Student->value);
        $this->recordAnswer($user, Carbon::parse('2026-08-20 09:00:00'));

        $this->assertSame(1, SendStreakWarningsAction::run());
        $this->assertSame(0, SendStreakWarningsAction::run());
        $this->assertSame(1, UserNotification::query()->where('type', 'streak.warning')->count());
    }

    public function test_command_runs_successfully(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 19:00:00'));

        $this->artisan('notification:streak-warnings')->assertSuccessful();
    }

    private function recordAnswer(User $user, Carbon $answeredAt): void
    {
        $session = QuestionSession::factory()->for($user)->create();

        QuestionAttempt::query()->create([
            'session_id' => $session->getKey(),
            'user_id' => $user->getKey(),
            'question_id' => $this->question->getKey(),
            'selected_option_ids' => [1],
            'is_correct' => true,
            'used_hint' => false,
            'time_spent_seconds' => 30,
            'flagged' => false,
            'answered_at' => $answeredAt,
        ]);
    }
}
