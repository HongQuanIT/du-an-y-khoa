<?php

declare(strict_types=1);

namespace Modules\Notification\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\Notification\Actions\CreateUserNotificationAction;
use Modules\Notification\Events\UserNotificationCreated;
use Modules\Notification\Jobs\FanOutSystemNotificationJob;
use Modules\Notification\Models\UserNotification;
use Tests\TestCase;

final class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_learner_can_open_notification_center(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);

        UserNotification::query()->create([
            'user_id' => $user->getKey(),
            'type' => 'system.broadcast',
            'category' => 'system',
            'title' => 'Bảo trì',
            'body' => 'Tối nay 23:00',
        ]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Bảo trì');
    }

    public function test_create_notification_broadcasts_realtime_event(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $user = User::factory()->create([
            'notification_prefs' => ['push_reminders' => true],
        ]);

        CreateUserNotificationAction::run(
            user: $user,
            type: 'session.completed',
            title: 'Xong phiên',
            body: 'Tóm tắt',
        );

        Event::assertDispatched(UserNotificationCreated::class);
    }

    public function test_system_broadcast_bypasses_preferences(): void
    {
        $user = User::factory()->create([
            'notification_prefs' => [
                'push_reminders' => false,
                'push_classroom' => false,
            ],
        ]);

        $result = CreateUserNotificationAction::run(
            user: $user,
            type: 'system.broadcast',
            title: 'Hệ thống',
            body: 'Tin bắt buộc',
            broadcast: false,
        );

        $this->assertNotNull($result);
        $this->assertSame('system', $result->category);
    }

    public function test_admin_can_queue_system_broadcast(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $admin->givePermissionTo(Permission::NotificationBroadcast->value);

        $learner = User::factory()->create();
        $learner->assignRole(Role::Student->value);

        $count = \Modules\Notification\Actions\BroadcastSystemNotificationAction::run(
            actor: $admin,
            title: 'Bảo trì đêm nay',
            body: 'Hệ thống tạm dừng 30 phút.',
            audience: 'learners',
            type: 'system.maintenance',
        );

        $this->assertSame(1, $count);
        Queue::assertPushed(FanOutSystemNotificationJob::class);
    }
}