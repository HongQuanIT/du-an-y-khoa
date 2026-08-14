<?php

declare(strict_types=1);

namespace Modules\Classroom\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;
use Tests\TestCase;

final class TeachClassroomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config(['classroom.open_hosting' => false]);
    }

    public function test_instructor_can_list_and_create_teach_classroom(): void
    {
        $instructor = $this->instructor();

        $this->actingAs($instructor)
            ->get(route('teach.classes.index'))
            ->assertOk()
            ->assertSee('Lớp của tôi')
            ->assertSee('Tạo lớp');

        $this->actingAs($instructor)
            ->get(route('teach.classes.create'))
            ->assertOk()
            ->assertSee('Loại buổi')
            ->assertSee('Chữa từ feedback');

        $this->actingAs($instructor)
            ->post(route('teach.classes.store'), [
                'title' => 'Chữa feedback Nội',
                'description' => 'Buổi chữa từ report QBank',
                'purpose' => ClassroomPurpose::FeedbackReview->value,
                'visibility' => ClassroomVisibility::Unlisted->value,
                'max_members' => 50,
            ])
            ->assertRedirect();

        $classroom = Classroom::query()->where('title', 'Chữa feedback Nội')->first();
        $this->assertNotNull($classroom);
        $this->assertSame(ClassroomPurpose::FeedbackReview, $classroom->purpose);
        $this->assertSame($instructor->id, $classroom->host_user_id);

        $this->actingAs($instructor)
            ->get(route('teach.classes.show', $classroom))
            ->assertOk()
            ->assertSee('Chữa feedback Nội')
            ->assertSee('Chưa gắn đề');
    }

    public function test_instructor_cannot_create_community_purpose_via_teach(): void
    {
        $instructor = $this->instructor();

        $this->actingAs($instructor)
            ->post(route('teach.classes.store'), [
                'title' => 'Lớp cộng đồng giả',
                'purpose' => ClassroomPurpose::CommunityReview->value,
                'visibility' => ClassroomVisibility::Public->value,
            ])
            ->assertSessionHasErrors('purpose');

        $this->assertDatabaseMissing('classrooms', [
            'title' => 'Lớp cộng đồng giả',
        ]);
    }

    public function test_instructor_cannot_open_community_classroom_on_teach(): void
    {
        $instructor = $this->instructor();
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::CommunityReview);

        $this->actingAs($instructor)
            ->get(route('teach.classes.show', $classroom))
            ->assertNotFound();
    }

    public function test_other_instructor_cannot_view_foreign_class(): void
    {
        $owner = $this->instructor(['email' => 'owner@example.com']);
        $other = $this->instructor(['email' => 'other@example.com']);
        $classroom = $this->seedClassroom($owner, ClassroomPurpose::ExamReview);

        $this->actingAs($other)
            ->get(route('teach.classes.show', $classroom))
            ->assertForbidden();
    }

    public function test_student_cannot_access_teach_classes(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('teach.classes.index'))
            ->assertRedirect();
    }

    /** @param  array<string, mixed>  $attributes */
    private function instructor(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::Instructor->value);

        return $user;
    }

    private function seedClassroom(User $host, ClassroomPurpose $purpose): Classroom
    {
        $classroom = Classroom::query()->create([
            'title' => 'Lớp test '.$purpose->value,
            'host_user_id' => $host->id,
            'purpose' => $purpose,
            'visibility' => ClassroomVisibility::Unlisted,
            'join_code' => 'TEST'.strtoupper(substr($purpose->value, 0, 4)),
            'status' => 'active',
        ]);

        ClassroomMember::query()->create([
            'classroom_id' => $classroom->id,
            'user_id' => $host->id,
            'role_in_class' => MemberRole::Host,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);

        return $classroom;
    }
}
