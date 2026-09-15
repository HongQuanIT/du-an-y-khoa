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
        $subject = $this->makeSubject(['name' => 'Nội tim mạch']);
        $this->makeLesson([
            'name' => 'Tăng huyết áp',
            'subjects' => [$subject],
            'organSystems' => [$organSystem],
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.curriculum.index'))
            ->assertOk()
            ->assertSee('Hệ tim mạch');

        $this->actingAsStaff($admin)
            ->get(route('admin.curriculum.index', ['tab' => 'subjects']))
            ->assertOk()
            ->assertSee('Nội tim mạch');

        $this->actingAsStaff($admin)
            ->get(route('admin.curriculum.index', ['tab' => 'lessons']))
            ->assertOk()
            ->assertSee('Tăng huyết áp');
    }

    public function test_catalog_paginates_twenty_items_and_filters_on_the_server(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject(['name' => 'Nội khoa']);
        $organSystem = $this->makeOrganSystem(['name' => 'Hệ tim mạch']);

        foreach (range(1, 25) as $i) {
            $this->makeLesson([
                'name' => sprintf('Bài %02d', $i),
                'subjects' => [$subject],
                'organSystems' => [$organSystem],
            ]);
        }

        $this->actingAsStaff($admin)
            ->get(route('admin.curriculum.index', ['tab' => 'lessons']))
            ->assertOk()
            ->assertSee('Bài 01', false)
            ->assertSee('Bài 20', false)
            ->assertDontSee('Bài 21', false);

        $this->actingAsStaff($admin)
            ->get(route('admin.curriculum.index', ['tab' => 'lessons', 'page' => 2]))
            ->assertOk()
            ->assertSee('Bài 21', false)
            ->assertDontSee('Bài 01', false);

        $this->actingAsStaff($admin)
            ->get(route('admin.curriculum.index', ['tab' => 'lessons', 'q' => 'Bài 21']))
            ->assertOk()
            ->assertSee('Bài 21', false)
            ->assertDontSee('Bài 01', false)
            ->assertDontSee('Bài 20', false);

        $this->actingAsStaff($admin)
            ->getJson(route('admin.curriculum.index', ['tab' => 'lessons', 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonFragment(['name' => 'Bài 21'])
            ->assertJsonMissing(['name' => 'Bài 01']);

        $this->actingAsStaff($admin)
            ->getJson(route('admin.curriculum.index', ['tab' => 'lessons', 'q' => 'Bài 21']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['name' => 'Bài 21']);
    }

    public function test_lesson_search_matches_only_name_or_slug(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject(['name' => 'Dược lý lâm sàng']);
        $organSystem = $this->makeOrganSystem(['name' => 'Hệ nội tiết']);
        $this->makeLesson([
            'name' => 'Suy tim',
            'slug' => 'suy-tim',
            'subjects' => [$subject],
            'organSystems' => [$organSystem],
        ]);

        $this->actingAsStaff($admin)
            ->getJson(route('admin.curriculum.index', ['tab' => 'lessons', 'q' => 'Suy tim']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['name' => 'Suy tim']);

        $this->actingAsStaff($admin)
            ->getJson(route('admin.curriculum.index', ['tab' => 'lessons', 'q' => 'suy-tim']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAsStaff($admin)
            ->getJson(route('admin.curriculum.index', ['tab' => 'lessons', 'q' => 'Dược lý']))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAsStaff($admin)
            ->getJson(route('admin.curriculum.index', ['tab' => 'lessons', 'q' => 'nội tiết']))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_lesson_filters_accept_multiple_subjects_and_organ_systems(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $internal = $this->makeSubject(['name' => 'Nội khoa']);
        $pharma = $this->makeSubject(['name' => 'Dược lý']);
        $surgery = $this->makeSubject(['name' => 'Ngoại khoa']);
        $cardio = $this->makeOrganSystem(['name' => 'Tim mạch']);
        $resp = $this->makeOrganSystem(['name' => 'Hô hấp']);
        $neuro = $this->makeOrganSystem(['name' => 'Thần kinh']);

        $this->makeLesson([
            'name' => 'Suy tim',
            'subjects' => [$internal],
            'organSystems' => [$cardio],
        ]);
        $this->makeLesson([
            'name' => 'Tương tác thuốc',
            'subjects' => [$pharma],
            'organSystems' => [$resp],
        ]);
        $this->makeLesson([
            'name' => 'Phẫu thuật não',
            'subjects' => [$surgery],
            'organSystems' => [$neuro],
        ]);

        $this->actingAsStaff($admin)
            ->getJson(route('admin.curriculum.index', [
                'tab' => 'lessons',
                'subject_ids' => [$internal->id, $pharma->id],
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['name' => 'Suy tim'])
            ->assertJsonFragment(['name' => 'Tương tác thuốc'])
            ->assertJsonMissing(['name' => 'Phẫu thuật não']);

        $this->actingAsStaff($admin)
            ->getJson(route('admin.curriculum.index', [
                'tab' => 'lessons',
                'organ_system_ids' => [$cardio->id, $neuro->id],
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['name' => 'Suy tim'])
            ->assertJsonFragment(['name' => 'Phẫu thuật não'])
            ->assertJsonMissing(['name' => 'Tương tác thuốc']);

        $this->actingAsStaff($admin)
            ->getJson(route('admin.curriculum.index', [
                'tab' => 'lessons',
                'subject_ids' => [$internal->id, $pharma->id],
                'organ_system_ids' => [$cardio->id],
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['name' => 'Suy tim']);
    }

    public function test_admin_cannot_create_catalog_with_duplicate_name(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $this->makeOrganSystem(['name' => 'Hệ tim mạch']);
        $this->makeSubject(['name' => 'Nội khoa']);

        $this->actingAsStaff($admin)
            ->from(route('admin.curriculum.index', ['tab' => 'organ-systems']))
            ->post(route('admin.curriculum.organ-systems.store'), [
                'name' => 'hệ tim mạch',
                'status' => TaxonomyStatus::Active->value,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('name');

        $this->actingAsStaff($admin)
            ->from(route('admin.curriculum.index', ['tab' => 'subjects']))
            ->post(route('admin.curriculum.subjects.store'), [
                'name' => 'Nội Khoa',
                'status' => TaxonomyStatus::Active->value,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('name');

        $this->assertSame(1, OrganSystem::query()->where('name', 'Hệ tim mạch')->count());
        $this->assertSame(1, Subject::query()->where('name', 'Nội khoa')->count());
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
            ])
            ->assertRedirect();

        $subject = Subject::query()->where('name', 'Hô hấp học')->firstOrFail();
        $this->assertSame(0, $subject->lessons()->count());

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.lessons.store'), [
                'name' => 'Viêm phổi cộng đồng',
                'status' => TaxonomyStatus::Active->value,
                'subject_ids' => [$subject->id],
                'organ_system_ids' => [$organSystem->id],
            ])
            ->assertRedirect();

        $lesson = Lesson::query()->where('name', 'Viêm phổi cộng đồng')->firstOrFail();
        $this->assertTrue($lesson->subjects()->whereKey($subject->id)->exists());
        $this->assertTrue($lesson->organSystems()->whereKey($organSystem->id)->exists());
    }

    public function test_admin_can_create_lesson_without_subject_or_organ_system(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject();
        $organSystem = $this->makeOrganSystem();

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.lessons.store'), [
                'name' => 'Chưa phân loại',
                'status' => TaxonomyStatus::Active->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $unlinked = Lesson::query()->where('name', 'Chưa phân loại')->firstOrFail();
        $this->assertSame(0, $unlinked->subjects()->count());
        $this->assertSame(0, $unlinked->organSystems()->count());

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.lessons.store'), [
                'name' => 'Chỉ có môn',
                'status' => TaxonomyStatus::Active->value,
                'subject_ids' => [$subject->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $subjectOnly = Lesson::query()->where('name', 'Chỉ có môn')->firstOrFail();
        $this->assertTrue($subjectOnly->subjects()->whereKey($subject->id)->exists());
        $this->assertSame(0, $subjectOnly->organSystems()->count());

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.lessons.store'), [
                'name' => 'Chỉ có hệ',
                'status' => TaxonomyStatus::Active->value,
                'organ_system_ids' => [$organSystem->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $osOnly = Lesson::query()->where('name', 'Chỉ có hệ')->firstOrFail();
        $this->assertSame(0, $osOnly->subjects()->count());
        $this->assertTrue($osOnly->organSystems()->whereKey($organSystem->id)->exists());
    }

    public function test_admin_can_update_a_lesson(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject();
        $organSystem = $this->makeOrganSystem();
        $lesson = $this->makeLesson([
            'name' => 'Tên cũ',
            'subjects' => [$subject],
            'organSystems' => [$organSystem],
        ]);

        $this->actingAsStaff($admin)
            ->put(route('admin.curriculum.lessons.update', $lesson), [
                'name' => 'Tên mới',
                'slug' => $lesson->slug,
                'status' => TaxonomyStatus::Inactive->value,
                'sort_order' => 5,
                'subject_ids' => [$subject->id],
                'organ_system_ids' => [$organSystem->id],
            ])
            ->assertRedirect();

        $lesson->refresh();
        $this->assertSame('Tên mới', $lesson->name);
        $this->assertSame(TaxonomyStatus::Inactive, $lesson->status);
        $this->assertSame(5, $lesson->sort_order);

        $this->actingAsStaff($admin)
            ->put(route('admin.curriculum.lessons.update', $lesson), [
                'name' => 'Tên mới',
                'slug' => $lesson->slug,
                'status' => TaxonomyStatus::Inactive->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $lesson->refresh();
        $this->assertSame(0, $lesson->subjects()->count());
        $this->assertSame(0, $lesson->organSystems()->count());
    }

    public function test_admin_can_attach_and_detach_subject_from_lesson(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject();
        $extra = $this->makeSubject(['name' => 'Dược lý']);
        $lesson = $this->makeLesson([
            'subjects' => [$subject],
            'organSystems' => [$this->makeOrganSystem()],
        ]);

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.lessons.subjects.attach', $lesson), [
                'subject_id' => $extra->id,
            ])
            ->assertRedirect();

        $this->assertTrue($lesson->subjects()->whereKey($extra->id)->exists());

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.lessons.subjects.detach', [$lesson, $extra]))
            ->assertRedirect();

        $this->assertFalse($lesson->subjects()->whereKey($extra->id)->exists());
        $this->assertTrue($lesson->subjects()->whereKey($subject->id)->exists());
    }

    public function test_subjects_json_includes_linked_lessons_for_drawer(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject(['name' => 'Nội khoa']);
        $lesson = $this->makeLesson([
            'name' => 'Suy tim',
            'subjects' => [$subject],
            'organSystems' => [$this->makeOrganSystem()],
        ]);

        $this->actingAsStaff($admin)
            ->getJson(route('admin.curriculum.index', ['tab' => 'subjects']))
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Nội khoa',
                'lessons_count' => 1,
            ])
            ->assertJsonPath('data.0.lessons.0.id', $lesson->id)
            ->assertJsonPath('data.0.lessons.0.name', 'Suy tim')
            ->assertJsonPath('data.0.attach_lessons_url', route('admin.curriculum.subjects.lessons.attach', $subject));
    }

    public function test_admin_can_attach_and_detach_lessons_from_subject_drawer(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject(['name' => 'Nội khoa']);
        $linked = $this->makeLesson([
            'name' => 'Đã gắn',
            'subjects' => [$subject],
            'organSystems' => [$this->makeOrganSystem()],
        ]);
        $extra = $this->makeLesson([
            'name' => 'Chưa gắn',
            'subjects' => [$this->makeSubject(['name' => 'Dược lý'])],
            'organSystems' => [$this->makeOrganSystem(['name' => 'Hô hấp'])],
        ]);

        $this->actingAsStaff($admin)
            ->postJson(route('admin.curriculum.subjects.lessons.attach', $subject), [
                'lesson_id' => $extra->id,
            ])
            ->assertOk()
            ->assertJsonPath('lessons_count', 2)
            ->assertJsonFragment(['name' => 'Chưa gắn']);

        $this->assertTrue($subject->lessons()->whereKey($extra->id)->exists());
        $this->assertTrue($subject->lessons()->whereKey($linked->id)->exists());

        $this->actingAsStaff($admin)
            ->deleteJson(route('admin.curriculum.subjects.lessons.detach', [$subject, $extra]))
            ->assertOk()
            ->assertJsonPath('lessons_count', 1)
            ->assertJsonMissing(['name' => 'Chưa gắn']);

        $this->assertFalse($subject->lessons()->whereKey($extra->id)->exists());
        $this->assertTrue($extra->subjects()->exists());
        $this->assertTrue($subject->lessons()->whereKey($linked->id)->exists());
    }

    public function test_admin_can_detach_the_last_subject_from_a_lesson(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject();
        $lesson = $this->makeLesson([
            'subjects' => [$subject],
            'organSystems' => [$this->makeOrganSystem()],
        ]);

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.lessons.subjects.detach', [$lesson, $subject]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $lesson->subjects()->count());
    }

    public function test_admin_can_sync_multiple_subjects_and_organ_systems_on_lesson_update(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $internal = $this->makeSubject(['name' => 'Nội khoa']);
        $pharma = $this->makeSubject(['name' => 'Dược lý']);
        $emergency = $this->makeSubject(['name' => 'Hồi sức cấp cứu']);
        $cardio = $this->makeOrganSystem(['name' => 'Tim mạch']);
        $resp = $this->makeOrganSystem(['name' => 'Hô hấp']);
        $lesson = $this->makeLesson([
            'name' => 'Suy tim',
            'subjects' => [$internal],
            'organSystems' => [$cardio],
        ]);

        $this->actingAsStaff($admin)
            ->put(route('admin.curriculum.lessons.update', $lesson), [
                'name' => 'Suy tim',
                'slug' => $lesson->slug,
                'status' => TaxonomyStatus::Active->value,
                'sort_order' => 0,
                'subject_ids' => [$internal->id, $pharma->id, $emergency->id],
                'organ_system_ids' => [$cardio->id, $resp->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            collect([$internal->id, $pharma->id, $emergency->id])->sort()->values()->all(),
            $lesson->fresh()->subjects()->pluck('subjects.id')->sort()->values()->all(),
        );
        $this->assertSame(
            collect([$cardio->id, $resp->id])->sort()->values()->all(),
            $lesson->fresh()->organSystems()->pluck('organ_systems.id')->sort()->values()->all(),
        );
    }

    public function test_admin_can_attach_and_detach_organ_system_from_lesson(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $osA = $this->makeOrganSystem(['name' => 'Tim mạch']);
        $osB = $this->makeOrganSystem(['name' => 'Nội tiết']);
        $lesson = $this->makeLesson([
            'name' => 'Giải phẫu tim',
            'subjects' => [$this->makeSubject()],
            'organSystems' => [$osA],
        ]);

        $this->actingAsStaff($admin)
            ->post(route('admin.curriculum.lessons.organ-systems.attach', $lesson), [
                'organ_system_ids' => [$osB->id],
            ])
            ->assertRedirect();

        $this->assertCount(2, $lesson->fresh()->organSystems);

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.lessons.organ-systems.detach', [$lesson, $osB]))
            ->assertRedirect();

        $this->assertFalse($lesson->organSystems()->whereKey($osB->id)->exists());
        $this->assertTrue($lesson->organSystems()->whereKey($osA->id)->exists());
    }

    public function test_admin_can_detach_the_last_organ_system_from_a_lesson(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $organSystem = $this->makeOrganSystem();
        $lesson = $this->makeLesson([
            'subjects' => [$this->makeSubject()],
            'organSystems' => [$organSystem],
        ]);

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.lessons.organ-systems.detach', [$lesson, $organSystem]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $lesson->organSystems()->count());
    }

    public function test_admin_can_delete_an_unused_organ_system_and_subject(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $organSystem = $this->makeOrganSystem(['name' => 'Hệ da']);
        $subject = $this->makeSubject(['name' => 'Da liễu']);

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.organ-systems.destroy', $organSystem))
            ->assertRedirect();
        $this->assertNull(OrganSystem::query()->find($organSystem->id));

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.subjects.destroy', $subject))
            ->assertRedirect();
        $this->assertNull(Subject::query()->find($subject->id));
    }

    public function test_admin_cannot_delete_organ_system_or_subject_when_it_is_the_only_link(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $organSystem = $this->makeOrganSystem(['name' => 'Hệ duy nhất']);
        $subject = $this->makeSubject(['name' => 'Môn duy nhất']);
        $this->makeLesson([
            'subjects' => [$subject],
            'organSystems' => [$organSystem],
        ]);

        $this->actingAsStaff($admin)
            ->from(route('admin.curriculum.index', ['tab' => 'organ-systems']))
            ->delete(route('admin.curriculum.organ-systems.destroy', $organSystem))
            ->assertRedirect()
            ->assertSessionHasErrors('delete');
        $this->assertNotNull(OrganSystem::query()->find($organSystem->id));

        $this->actingAsStaff($admin)
            ->from(route('admin.curriculum.index', ['tab' => 'subjects']))
            ->delete(route('admin.curriculum.subjects.destroy', $subject))
            ->assertRedirect()
            ->assertSessionHasErrors('delete');
        $this->assertNotNull(Subject::query()->find($subject->id));
    }

    public function test_admin_can_delete_catalog_node_when_lessons_have_another_link(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $keepOs = $this->makeOrganSystem(['name' => 'Hệ giữ lại']);
        $dropOs = $this->makeOrganSystem(['name' => 'Hệ xoá']);
        $keepSubject = $this->makeSubject(['name' => 'Môn giữ lại']);
        $dropSubject = $this->makeSubject(['name' => 'Môn xoá']);
        $this->makeLesson([
            'subjects' => [$keepSubject, $dropSubject],
            'organSystems' => [$keepOs, $dropOs],
        ]);

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.organ-systems.destroy', $dropOs))
            ->assertRedirect();
        $this->assertNull(OrganSystem::query()->find($dropOs->id));

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.subjects.destroy', $dropSubject))
            ->assertRedirect();
        $this->assertNull(Subject::query()->find($dropSubject->id));
    }

    public function test_admin_cannot_create_lesson_with_duplicate_name(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject();
        $organSystem = $this->makeOrganSystem();
        $this->makeLesson([
            'name' => 'Suy tim',
            'subjects' => [$subject],
            'organSystems' => [$organSystem],
        ]);

        $this->actingAsStaff($admin)
            ->from(route('admin.curriculum.index', ['tab' => 'lessons']))
            ->post(route('admin.curriculum.lessons.store'), [
                'name' => 'suy tim',
                'status' => TaxonomyStatus::Active->value,
                'subject_ids' => [$subject->id],
                'organ_system_ids' => [$organSystem->id],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('name');
    }

    public function test_admin_cannot_update_lesson_to_duplicate_name_or_slug(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $subject = $this->makeSubject();
        $organSystem = $this->makeOrganSystem();
        $existing = $this->makeLesson([
            'name' => 'Suy tim',
            'slug' => 'suy-tim',
            'subjects' => [$subject],
            'organSystems' => [$organSystem],
        ]);
        $lesson = $this->makeLesson([
            'name' => 'Viêm phổi',
            'slug' => 'viem-phoi',
            'subjects' => [$subject],
            'organSystems' => [$organSystem],
        ]);

        $this->actingAsStaff($admin)
            ->from(route('admin.curriculum.index', ['tab' => 'lessons', 'focus' => $lesson->id]))
            ->put(route('admin.curriculum.lessons.update', $lesson), [
                'name' => 'suy tim',
                'slug' => $lesson->slug,
                'status' => TaxonomyStatus::Active->value,
                'subject_ids' => [$subject->id],
                'organ_system_ids' => [$organSystem->id],
                '_catalog' => 'lessons',
                '_editing_id' => $lesson->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('name');

        $lesson->refresh();
        $this->assertSame('Viêm phổi', $lesson->name);
        $this->assertSame('viem-phoi', $lesson->slug);

        $this->actingAsStaff($admin)
            ->from(route('admin.curriculum.index', ['tab' => 'lessons', 'focus' => $lesson->id]))
            ->put(route('admin.curriculum.lessons.update', $lesson), [
                'name' => 'Viêm phổi cộng đồng',
                'slug' => $existing->slug,
                'status' => TaxonomyStatus::Active->value,
                'subject_ids' => [$subject->id],
                'organ_system_ids' => [$organSystem->id],
                '_catalog' => 'lessons',
                '_editing_id' => $lesson->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('slug');

        $lesson->refresh();
        $this->assertSame('Viêm phổi', $lesson->name);
        $this->assertSame('viem-phoi', $lesson->slug);
        $this->assertSame(1, Lesson::query()->where('slug', 'suy-tim')->count());
    }

    public function test_admin_can_delete_an_unused_lesson(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $lesson = $this->makeLesson([
            'name' => 'Bài xoá được',
            'subjects' => [$this->makeSubject()],
            'organSystems' => [$this->makeOrganSystem()],
        ]);

        $this->actingAsStaff($admin)
            ->delete(route('admin.curriculum.lessons.destroy', $lesson))
            ->assertRedirect();

        $this->assertNull(Lesson::query()->find($lesson->id));
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
