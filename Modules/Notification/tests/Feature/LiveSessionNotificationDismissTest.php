<?php

declare(strict_types=1);

namespace Modules\Notification\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Events\LiveSessionEnded;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Notification\Models\UserNotification;
use Modules\Notification\View\Composers\HeaderNotificationsComposer;
use Tests\TestCase;

final class LiveSessionNotificationDismissTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_live_session_ended_event_marks_live_notifications_as_read(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole(Role::Instructor);

        $student = User::factory()->create();
        $student->assignRole(Role::Student);

        $classroom = Classroom::query()->create([
            'host_user_id' => $teacher->getKey(),
            'title' => 'Lớp Chữa Đề',
            'purpose' => \Modules\Classroom\Enums\ClassroomPurpose::ExamReview,
            'visibility' => \Modules\Classroom\Enums\ClassroomVisibility::Public,
            'status' => ClassroomStatus::Active,
        ]);

        $session = LiveSession::query()->create([
            'classroom_id' => $classroom->getKey(),
            'title' => 'Buổi Live 1',
            'status' => LiveSessionStatus::Ended,
        ]);

        $notif = UserNotification::query()->create([
            'user_id' => $student->getKey(),
            'type' => 'live.started',
            'title' => 'Lớp đang live',
            'body' => 'Buổi live bắt đầu',
            'data' => [
                'session_id' => $session->getKey(),
            ],
        ]);

        $this->assertNull($notif->read_at);

        event(new LiveSessionEnded($session));

        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_composer_skips_and_dismisses_stale_ended_live_session_notifications(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student);

        $classroom = Classroom::query()->create([
            'host_user_id' => $student->getKey(),
            'title' => 'Lớp Test',
            'purpose' => \Modules\Classroom\Enums\ClassroomPurpose::ExamReview,
            'visibility' => \Modules\Classroom\Enums\ClassroomVisibility::Public,
            'status' => ClassroomStatus::Active,
        ]);

        $endedSession = LiveSession::query()->create([
            'classroom_id' => $classroom->getKey(),
            'title' => 'Buổi Live Cũ',
            'status' => LiveSessionStatus::Ended,
        ]);

        $staleNotif = UserNotification::query()->create([
            'user_id' => $student->getKey(),
            'type' => 'live.started',
            'title' => 'Lớp đang live',
            'body' => 'Buổi live cũ',
            'data' => [
                'session_id' => $endedSession->getKey(),
            ],
        ]);

        $this->actingAs($student);

        $view = view('notification::partials.bell');
        (new HeaderNotificationsComposer())->compose($view);

        $data = $view->getData();
        $this->assertNull($data['importantFlyoutNotification']);
        $this->assertNotNull($staleNotif->fresh()->read_at);
    }

    public function test_instructor_dismissing_flyout_persists_and_does_not_show_again(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole(Role::Instructor->value);
        $notification = UserNotification::query()->create([
            'user_id' => $instructor->getKey(),
            'type' => 'classroom.approved',
            'title' => 'Lớp đã được duyệt',
            'body' => 'Lớp của bạn đã được duyệt.',
            'action_url' => '/teach/classes/1',
        ]);

        $this->actingAs($instructor)
            ->postJson(route('notifications.read', $notification))
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);

        $view = view('notification::partials.bell');
        (new HeaderNotificationsComposer())->compose($view);

        $this->assertNull($view->getData()['importantFlyoutNotification']);
    }
}
