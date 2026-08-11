<?php

declare(strict_types=1);

namespace Modules\Notification\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Notification\Actions\SendStudyPlanReminderEmailsAction;
use Modules\Notification\Mail\StudyPlanReminderMail;
use Modules\Notification\Models\StudyPlanReminderLog;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Enums\TaskType;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;
use Tests\TestCase;

final class StudyPlanReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminder_when_email_plan_enabled(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'notification_prefs' => ['email_plan' => true],
        ]);
        $plan = StudyPlan::factory()->for($user)->create(['status' => PlanStatus::Active]);
        StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => now()->toDateString(),
            'type' => TaskType::Questions,
            'status' => TaskStatus::Pending,
            'target' => 10,
            'done' => 0,
        ]);

        $sent = SendStudyPlanReminderEmailsAction::run();

        $this->assertSame(1, $sent);
        Mail::assertQueued(StudyPlanReminderMail::class, fn (StudyPlanReminderMail $mail): bool => $mail->hasTo($user->email));
        $this->assertTrue(
            StudyPlanReminderLog::query()->where('user_id', $user->getKey())->exists()
        );
    }

    public function test_skips_when_email_plan_disabled(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'notification_prefs' => ['email_plan' => false],
        ]);
        $plan = StudyPlan::factory()->for($user)->create(['status' => PlanStatus::Active]);
        StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => now()->toDateString(),
            'status' => TaskStatus::Pending,
        ]);

        $sent = SendStudyPlanReminderEmailsAction::run();

        $this->assertSame(0, $sent);
        Mail::assertNothingQueued();
    }

    public function test_does_not_send_duplicate_on_same_day(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'notification_prefs' => ['email_plan' => true],
        ]);
        $plan = StudyPlan::factory()->for($user)->create(['status' => PlanStatus::Active]);
        StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => now()->toDateString(),
            'status' => TaskStatus::Pending,
        ]);

        SendStudyPlanReminderEmailsAction::run();
        SendStudyPlanReminderEmailsAction::run();

        Mail::assertQueued(StudyPlanReminderMail::class, 1);
    }

    public function test_command_runs_successfully(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'notification_prefs' => ['email_plan' => true],
        ]);
        $plan = StudyPlan::factory()->for($user)->create(['status' => PlanStatus::Active]);
        StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => now()->toDateString(),
            'status' => TaskStatus::Pending,
        ]);

        $this->artisan('notification:study-plan-reminders')
            ->assertSuccessful();

        Mail::assertQueued(StudyPlanReminderMail::class);
    }
}
