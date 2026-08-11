<?php

declare(strict_types=1);

namespace Modules\Notification\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Models\UserNotification;
use Modules\QuestionBank\Data\QuestionSessionProgressed;
use Tests\TestCase;

final class SessionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_completed_creates_in_app_notification(): void
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

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $user->getKey())
                ->where('type', 'session.completed')
                ->exists()
        );
    }

    public function test_session_notification_respects_push_preference(): void
    {
        $user = User::factory()->create([
            'notification_prefs' => [
                'push_reminders' => false,
            ],
        ]);

        event(new QuestionSessionProgressed(
            userId: (int) $user->getKey(),
            sessionId: '00000000-0000-0000-0000-000000000001',
            completed: true,
        ));

        $this->assertFalse(
            UserNotification::query()->where('user_id', $user->getKey())->exists()
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
