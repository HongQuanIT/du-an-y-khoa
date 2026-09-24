<?php

declare(strict_types=1);

namespace Modules\Editor\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\Notification\Actions\BroadcastSystemNotificationAction;
use Modules\Notification\Models\UserNotification;
use Tests\TestCase;

final class EditorNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_editor_can_view_notifications_index_with_editor_layout(): void
    {
        $editor = $this->editor('Biên tập viên 1');

        UserNotification::query()->create([
            'user_id' => $editor->getKey(),
            'type' => 'question.approved',
            'category' => 'result',
            'title' => 'Câu hỏi đã được duyệt',
            'body' => 'Câu hỏi TEST-001 của bạn đã được phê duyệt.',
            'action_url' => route('editor.dashboard'),
        ]);

        $response = $this->actingAsEditor($editor)
            ->get(route('editor.notifications.index'));

        $response->assertOk()
            ->assertSee('Trung tâm thông báo')
            ->assertSee('Câu hỏi đã được duyệt')
            ->assertSee('TEST-001')
            ->assertSee('Cổng biên tập nội dung');
    }

    public function test_editor_header_shows_notification_bell_and_unread_count(): void
    {
        $editor = $this->editor('Biên tập viên 2');

        UserNotification::query()->create([
            'user_id' => $editor->getKey(),
            'type' => 'question.rejected',
            'category' => 'result',
            'title' => 'Câu hỏi cần chỉnh sửa',
            'body' => 'Vui lòng bổ sung giải thích chi tiết cho đáp án.',
            'action_url' => route('editor.dashboard'),
        ]);

        $response = $this->actingAsEditor($editor)
            ->get(route('editor.dashboard'));

        $response->assertOk()
            ->assertSee('data-notification-bell', false)
            ->assertSee('Câu hỏi cần chỉnh sửa')
            ->assertSee('notificationsOpen');
    }

    public function test_editor_can_mark_notification_as_read(): void
    {
        $editor = $this->editor('Biên tập viên 3');

        $notif = UserNotification::query()->create([
            'user_id' => $editor->getKey(),
            'type' => 'editor.assigned',
            'category' => 'system',
            'title' => 'Phân công biên tập mới',
            'body' => 'Bạn được phân công bộ câu hỏi Dược lý.',
            'action_url' => route('editor.dashboard'),
        ]);

        $this->actingAsEditor($editor)
            ->postJson(route('notifications.read', $notif))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_editor_can_mark_all_notifications_as_read(): void
    {
        $editor = $this->editor('Biên tập viên 4');

        UserNotification::query()->create([
            'user_id' => $editor->getKey(),
            'type' => 'question.feedback',
            'category' => 'support',
            'title' => 'Góp ý câu hỏi',
            'body' => 'Hình ảnh đáp án C cần tăng độ tương phản.',
        ]);

        $this->actingAsEditor($editor)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, UserNotification::query()->where('user_id', $editor->getKey())->whereNull('read_at')->count());
    }

    public function test_admin_broadcast_to_editors_delivers_to_content_editors(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::SuperAdmin->value);

        $editor1 = $this->editor('Editor 1');
        $editor2 = $this->editor('Editor 2');
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $action = app(BroadcastSystemNotificationAction::class);
        $total = $action->handle(
            actor: $admin,
            title: 'Họp ban biên tập',
            body: 'Cuộc họp diễn ra lúc 15:00 hôm nay.',
            audience: 'editors',
        );

        $this->assertSame(2, $total);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $editor1->getKey(),
            'title' => 'Họp ban biên tập',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $editor2->getKey(),
            'title' => 'Họp ban biên tập',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $student->getKey(),
            'title' => 'Họp ban biên tập',
        ]);
    }

    private function editor(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole(Role::ContentEditor->value);
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [],
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function actingAsEditor(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorSession::KEY => now()->timestamp]);
    }
}
