<?php

declare(strict_types=1);

namespace Modules\Notification\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Classroom\Actions\CreateClassroomAction;
use Modules\Classroom\Actions\StartLiveSessionAction;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Notification\Models\UserNotification;
use Modules\QuestionBank\Data\QuestionSessionProgressed;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

final class SessionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_class_always_notifies_active_students(): void
    {
        SpatieRole::findOrCreate(Role::Student->value, 'web');

        $host = User::factory()->create();
        $student = User::factory()->create([
            'notification_prefs' => ['push_classroom' => false],
        ]);
        $student->assignRole(Role::Student->value);
        $studentOutsideClass = User::factory()->create();
        $studentOutsideClass->assignRole(Role::Student->value);

        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Chữa đề Nội khoa',
            'visibility' => 'public',
        ]);
        $classroom->update(['status' => ClassroomStatus::Active]);
        $classroom->members()->create([
            'user_id' => $student->getKey(),
            'role_in_class' => MemberRole::Member,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);
        $session = $classroom->sessions()->create([
            'title' => 'Buổi chữa đề số 1',
            'status' => LiveSessionStatus::Scheduled,
        ]);

        app(StartLiveSessionAction::class)->handle($classroom, $session);

        $notification = UserNotification::query()
            ->where('user_id', $student->getKey())
            ->where('type', 'live.started')
            ->firstOrFail();

        $this->assertSame('Lớp đang live', $notification->title);
        $this->assertStringContainsString($classroom->title, $notification->body);
        $this->assertSame(
            route('classroom.live', [$classroom, $session]),
            $notification->action_url,
        );
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $studentOutsideClass->getKey(),
            'type' => 'live.started',
            'action_url' => route('classroom.show', $classroom),
        ]);
        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $host->getKey())
                ->where('type', 'live.started')
                ->exists(),
        );
    }

    public function test_session_completed_does_not_create_in_app_notification(): void
    {
        $user = User::factory()->create([
            'notification_prefs' => [
                'push_reminders' => true,
            ],
        ]);

        event(new QuestionSessionProgressed(
            userId: (int) $user->getKey(),
            sessionId: '00000000-0000-0000-0000-000000000001',
            completed: true,
        ));

        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $user->getKey())
                ->where('type', 'session.completed')
                ->exists()
        );
    }

    public function test_user_can_mark_notification_read(): void
    {
        $user = User::factory()->create();
        $notification = UserNotification::query()->create([
            'user_id' => $user->getKey(),
            'type' => 'session.completed',
            'title' => 'Test',
            'body' => 'Body',
        ]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
