<?php

declare(strict_types=1);

namespace Modules\Classroom\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TeachProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_instructor_can_view_profile_hub(): void
    {
        $instructor = $this->instructor();

        $this->actingAs($instructor)
            ->get(route('teach.profile.show'))
            ->assertOk()
            ->assertSee('Hồ sơ giảng dạy')
            ->assertSee('Liên hệ')
            ->assertSee('Bảo mật')
            ->assertSee('Giao diện');
    }

    public function test_instructor_can_update_teaching_profile(): void
    {
        $instructor = $this->instructor();

        $this->actingAs($instructor)
            ->put(route('teach.profile.update'), [
                'name' => 'GV Nguyễn Văn A',
                'career_role' => 'Giảng viên',
                'specialty' => 'Nội khoa',
                'institution' => 'ĐHYD TP.HCM',
                'headline' => 'Chữa đề Nội khoa cho sinh viên năm 5–6.',
            ])
            ->assertRedirect(route('teach.profile.show'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $instructor->id,
            'name' => 'GV Nguyễn Văn A',
            'specialty' => 'Nội khoa',
            'institution' => 'ĐHYD TP.HCM',
        ]);
    }

    public function test_instructor_can_change_password(): void
    {
        $instructor = $this->instructor(['password' => Hash::make('password')]);

        $this->actingAs($instructor)
            ->put(route('teach.profile.password'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('teach.profile.show', ['tab' => 'security']))
            ->assertSessionHas('status');

        $instructor->refresh();
        $this->assertTrue(Hash::check('new-password-123', $instructor->password));
    }

    public function test_instructor_can_upload_avatar(): void
    {
        Storage::fake('public');
        $instructor = $this->instructor();

        $this->actingAs($instructor)
            ->put(route('teach.profile.avatar'), [
                'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
            ])
            ->assertRedirect(route('teach.profile.show'))
            ->assertSessionHas('status');

        $instructor->refresh();
        $this->assertNotNull($instructor->avatar_path);
        Storage::disk('public')->assertExists($instructor->avatar_path);
    }

    public function test_student_cannot_access_teach_profile(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('teach.profile.show'))
            ->assertRedirect();
    }

    public function test_guest_is_redirected_to_teach_login(): void
    {
        $this->get(route('teach.profile.show'))
            ->assertRedirect();
    }

    /** @param  array<string, mixed>  $attributes */
    private function instructor(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::Instructor->value);

        return $user;
    }
}
