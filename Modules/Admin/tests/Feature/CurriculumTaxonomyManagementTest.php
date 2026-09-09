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
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Subject;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class CurriculumTaxonomyManagementTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_index_lists_the_three_curriculum_levels(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $organSystem = $this->makeOrganSystem(['name' => 'Hệ tim mạch']);
        $subject = $this->makeSubject(['name' => 'Nội tim mạch', 'organSystems' => [$organSystem]]);
        $this->makeLesson(['name' => 'Tăng huyết áp', 'subjects' => [$subject]]);

        $this->actingAsStaff($admin)
            ->get(route('admin.curriculum.index'))
            ->assertOk()
            ->assertSee('Hệ tim mạch')
            ->assertSee('Nội tim mạch')
            ->assertSee('Tăng huyết áp');
    }

    public function test_admin_can_create_each_level(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.organ-systems.store'), [
                'name' => 'Hệ hô hấp',
                'status' => TaxonomyStatus::Active->value,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $organSystem = OrganSystem::query()->where('name', 'Hệ hô hấp')->firstOrFail();
        $this->assertSame('he-ho-hap', $organSystem->slug);

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.subjects.store'), [
                'name' => 'Hô hấp học',
                'status' => TaxonomyStatus::Active->value,
                'organ_system_ids' => [$organSystem->id],
            ])
            ->assertRedirect();

        $subject = Subject::query()->where('name', 'Hô hấp học')->firstOrFail();
        $this->assertTrue($subject->organSystems()->whereKey($organSystem->id)->exists());

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.lessons.store'), [
                'name' => 'Viêm phổi cộng đồng',
                'status' => TaxonomyStatus::Active->value,
                'subject_ids' => [$subject->id],
            ])
            ->assertRedirect();

        $lesson = Lesson::query()->where('name', 'Viêm phổi cộng đồng')->firstOrFail();
        $this->assertTrue($lesson->subjects()->whereKey($subject->id)->exists());
    }

    public function test_admin_can_update_a_lesson(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $lesson = $this->makeLesson(['name' => 'Tên cũ']);

        $this->actingAsStaff($admin)
            ->put(route('admin.curriculum.lessons.update', $lesson), [
                'name' => 'Tên mới',
                'slug' => $lesson->slug,
                'status' => TaxonomyStatus::Inactive->value,
                'sort_order' => 5,
            ])
            ->assertRedirect();

        $lesson->refresh();
        $this->assertSame('Tên mới', $lesson->name);
        $this->assertSame(TaxonomyStatus::Inactive, $lesson->status);
        $this->assertSame(5, $lesson->sort_order);
    }

    public function test_admin_can_attach_and_detach_organ_system_from_subject(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $organSystem = $this->makeOrganSystem();
        $subject = $this->makeSubject();

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.subjects.organ-systems.attach', $subject), [
                'organ_system_id' => $organSystem->id,
            ])
            ->assertRedirect();

        $this->assertTrue($subject->organSystems()->whereKey($organSystem->id)->exists());

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.subjects.organ-systems.detach', [$subject, $organSystem]))
            ->assertRedirect();

        $this->assertFalse($subject->organSystems()->whereKey($organSystem->id)->exists());
    }

    public function test_admin_can_sync_multiple_organ_systems_on_subject_update(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $cardio = $this->makeOrganSystem(['name' => 'Tim mạch']);
        $resp = $this->makeOrganSystem(['name' => 'Hô hấp']);
        $neuro = $this->makeOrganSystem(['name' => 'Thần kinh']);
        $subject = $this->makeSubject(['name' => 'Nội khoa', 'organSystems' => [$cardio]]);

        $this->actingAsStaff($admin)
            ->put(route('admin.curriculum.subjects.update', $subject), [
                'name' => 'Nội khoa',
                'slug' => $subject->slug,
                'status' => TaxonomyStatus::Active->value,
                'sort_order' => 0,
                'organ_system_ids' => [$cardio->id, $resp->id, $neuro->id],
            ])
            ->assertRedirect();

        $linked = $subject->fresh()->organSystems()->pluck('organ_systems.id')->sort()->values()->all();
        $this->assertSame(
            collect([$cardio->id, $resp->id, $neuro->id])->sort()->values()->all(),
            $linked,
        );
    }

    public function test_admin_can_attach_and_detach_subject_from_lesson(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject();
        $lesson = $this->makeLesson();

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.lessons.subjects.attach', $lesson), [
                'subject_id' => $subject->id,
            ])
            ->assertRedirect();

        $this->assertTrue($lesson->subjects()->whereKey($subject->id)->exists());

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.lessons.subjects.detach', [$lesson, $subject]))
            ->assertRedirect();

        $this->assertFalse($lesson->subjects()->whereKey($subject->id)->exists());
    }

    public function test_admin_can_sync_multiple_subjects_on_lesson_update(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $internal = $this->makeSubject(['name' => 'Nội khoa']);
        $pharma = $this->makeSubject(['name' => 'Dược lý']);
        $emergency = $this->makeSubject(['name' => 'Hồi sức cấp cứu']);
        $lesson = $this->makeLesson(['name' => 'Suy tim', 'subjects' => [$internal]]);

        $this->actingAsStaff($admin)
            ->put(route('admin.curriculum.lessons.update', $lesson), [
                'name' => 'Suy tim',
                'slug' => $lesson->slug,
                'status' => TaxonomyStatus::Active->value,
                'sort_order' => 0,
                'subject_ids' => [$internal->id, $pharma->id, $emergency->id],
            ])
            ->assertRedirect();

        $linked = $lesson->fresh()->subjects()->pluck('subjects.id')->sort()->values()->all();
        $this->assertSame(
            collect([$internal->id, $pharma->id, $emergency->id])->sort()->values()->all(),
            $linked,
        );
    }

    public function test_admin_can_bulk_attach_organ_systems_and_subjects(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $osA = $this->makeOrganSystem(['name' => 'Tim mạch']);
        $osB = $this->makeOrganSystem(['name' => 'Nội tiết']);
        $subject = $this->makeSubject(['name' => 'Sinh lý']);

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.subjects.organ-systems.attach', $subject), [
                'organ_system_ids' => [$osA->id, $osB->id],
            ])
            ->assertRedirect();

        $this->assertCount(2, $subject->fresh()->organSystems);

        $subjA = $this->makeSubject(['name' => 'Giải phẫu']);
        $subjB = $this->makeSubject(['name' => 'Ngoại khoa']);
        $lesson = $this->makeLesson(['name' => 'Giải phẫu tim']);

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.lessons.subjects.attach', $lesson), [
                'subject_ids' => [$subjA->id, $subjB->id],
            ])
            ->assertRedirect();

        $this->assertCount(2, $lesson->fresh()->subjects);
    }

    public function test_guest_cannot_access_management_page(): void
    {
        $this->get(route('admin.curriculum.index'))->assertRedirect();
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }
}
