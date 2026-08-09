<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\Classroom\Actions\CreateClassroomAction;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\LiveSession;
use Tests\TestCase;

final class AdminClassroomOversightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_list_and_archive_classrooms(): void
    {
        $admin = $this->staffWith2fa(Role::Admin);
        $host = User::factory()->create();
        $host->assignRole(Role::Student->value);

        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Lớp chữa Step 1',
            'purpose' => ClassroomPurpose::FeedbackReview->value,
        ]);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSee('Lớp chữa Step 1', false)
            ->assertSee('Chữa từ feedback', false);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->post(route('admin.classrooms.archive', $classroom))
            ->assertRedirect();

        $this->assertSame(ClassroomStatus::Archived, $classroom->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'classroom.archive',
        ]);
    }

    public function test_admin_can_force_end_live_session(): void
    {
        $admin = $this->staffWith2fa(Role::SuperAdmin);
        $host = User::factory()->create();
        $host->assignRole(Role::Instructor->value);

        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Live đang chạy',
            'purpose' => ClassroomPurpose::ExamReview->value,
        ]);

        $session = LiveSession::query()->create([
            'classroom_id' => $classroom->getKey(),
            'title' => 'Buổi 1',
            'scheduled_at' => now()->subHour(),
            'started_at' => now()->subMinutes(10),
            'status' => LiveSessionStatus::Live,
            'livekit_room_name' => 'test-room',
        ]);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->post(route('admin.classrooms.force-end', $classroom))
            ->assertRedirect();

        $this->assertSame(LiveSessionStatus::Ended, $session->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'classroom.live.force_end',
        ]);
    }

    public function test_content_editor_cannot_oversee_classrooms(): void
    {
        $editor = $this->staffWith2fa(Role::ContentEditor);

        $this->actingAs($editor)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('admin.classrooms.index'))
            ->assertForbidden();
    }

    private function staffWith2fa(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $secret = (new TotpService)->generateSecret();

        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => $secret,
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);

        return $user;
    }
}
