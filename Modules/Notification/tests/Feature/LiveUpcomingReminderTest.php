<?php

declare(strict_types=1);

namespace Modules\Notification\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;
use Modules\Classroom\Models\LiveSession;
use Modules\Notification\Actions\SendLiveUpcomingRemindersAction;
use Modules\Notification\Models\LiveUpcomingReminderLog;
use Modules\Notification\Models\UserNotification;
use Tests\TestCase;

final class LiveUpcomingReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['notification.live_upcoming.lead_minutes' => 30]);
    }

    public function test_sends_upcoming_reminder_within_lead_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 18:00:00'));

        [$classroom, $session, $member] = $this->classroomWithUpcoming(now()->addMinutes(20));

        $sent = SendLiveUpcomingRemindersAction::run();

        $this->assertSame(2, $sent); // host + member
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $member->getKey())
                ->where('type', 'live.upcoming')
                ->exists()
        );
        $this->assertTrue(
            LiveUpcomingReminderLog::query()
                ->where('live_session_id', $session->getKey())
                ->where('user_id', $member->getKey())
                ->exists()
        );
    }

    public function test_does_not_duplicate_upcoming_reminder(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 18:00:00'));

        [, $session, $member] = $this->classroomWithUpcoming(now()->addMinutes(15));

        $this->assertSame(2, SendLiveUpcomingRemindersAction::run());
        $this->assertSame(0, SendLiveUpcomingRemindersAction::run());

        $this->assertSame(
            2,
            UserNotification::query()
                ->where('type', 'live.upcoming')
                ->count()
        );
        $this->assertSame(2, LiveUpcomingReminderLog::query()->where('live_session_id', $session->getKey())->count());
    }

    public function test_skips_sessions_outside_lead_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 18:00:00'));

        $this->classroomWithUpcoming(now()->addHours(2));

        $this->assertSame(0, SendLiveUpcomingRemindersAction::run());
        $this->assertSame(0, UserNotification::query()->where('type', 'live.upcoming')->count());
    }

    public function test_command_runs_successfully(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 18:00:00'));
        $this->classroomWithUpcoming(now()->addMinutes(10));

        $this->artisan('notification:live-upcoming')->assertSuccessful();
    }

    /**
     * @return array{0: Classroom, 1: LiveSession, 2: User}
     */
    private function classroomWithUpcoming(Carbon $scheduledAt): array
    {
        $host = User::factory()->create();
        $host->assignRole(Role::Instructor->value);

        $member = User::factory()->create([
            'notification_prefs' => ['push_classroom' => true],
        ]);
        $member->assignRole(Role::Student->value);

        $classroom = Classroom::query()->create([
            'title' => 'Lớp Tim mạch',
            'host_user_id' => $host->getKey(),
            'purpose' => ClassroomPurpose::ExamReview,
            'visibility' => ClassroomVisibility::Public,
            'status' => ClassroomStatus::Active,
        ]);

        ClassroomMember::query()->create([
            'classroom_id' => $classroom->getKey(),
            'user_id' => $host->getKey(),
            'role_in_class' => MemberRole::Host,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);

        ClassroomMember::query()->create([
            'classroom_id' => $classroom->getKey(),
            'user_id' => $member->getKey(),
            'role_in_class' => MemberRole::Member,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);

        $session = LiveSession::query()->create([
            'classroom_id' => $classroom->getKey(),
            'title' => 'Buổi chữa đề',
            'status' => LiveSessionStatus::Scheduled,
            'scheduled_at' => $scheduledAt,
        ]);

        return [$classroom, $session, $member];
    }
}
