<?php

declare(strict_types=1);

namespace Modules\Classroom\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Classroom\Actions\CreateClassroomAction;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\RecordingStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveRecording;
use Modules\Classroom\Models\LiveSession;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

final class ClassroomCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Role::values() as $role) {
            SpatieRole::findOrCreate($role, 'web');
        }

        config(['classroom.open_hosting' => true]);
    }

    public function test_catalog_shows_enriched_class_card_after_admin_approval(): void
    {
        $host = User::factory()->create([
            'name' => 'Dr. Host',
            'specialty' => 'Nội khoa',
            'institution' => 'BV Demo',
        ]);
        $host->assignRole(Role::Instructor->value);

        $learner = User::factory()->create();
        $learner->assignRole(Role::Student->value);

        $classroom = $this->approvedClassroom($host, [
            'title' => 'Chữa đề Tim mạch',
            'description' => 'Buổi chữa cộng đồng',
            'visibility' => 'public',
            'purpose' => ClassroomPurpose::ExamReview->value,
        ]);

        LiveSession::query()->create([
            'classroom_id' => $classroom->getKey(),
            'title' => 'Buổi 1',
            'scheduled_at' => now()->addDay(),
            'status' => LiveSessionStatus::Scheduled,
        ]);

        $this->actingAs($learner)
            ->get(route('classroom.index'))
            ->assertOk()
            ->assertSee('Lớp học')
            ->assertSee('Chữa đề Tim mạch')
            ->assertSee('Chữa đề thi')
            ->assertSee('Dr. Host')
            ->assertSee('Nội khoa')
            ->assertSee('Sắp live');
    }

    public function test_pending_classroom_is_hidden_from_learner_catalog(): void
    {
        $host = User::factory()->create(['name' => 'Pending Host']);
        $host->assignRole(Role::Instructor->value);

        $learner = User::factory()->create();
        $learner->assignRole(Role::Student->value);

        app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Lớp chưa duyệt',
            'visibility' => 'public',
            'purpose' => ClassroomPurpose::FeedbackReview->value,
        ]);

        $this->actingAs($learner)
            ->get(route('classroom.index'))
            ->assertOk()
            ->assertDontSee('Lớp chưa duyệt');
    }

    public function test_live_filter_lists_only_live_classrooms(): void
    {
        $host = User::factory()->create();
        $host->assignRole(Role::Instructor->value);

        $liveClass = $this->approvedClassroom($host, [
            'title' => 'Lớp đang live',
            'visibility' => 'public',
        ]);

        LiveSession::query()->create([
            'classroom_id' => $liveClass->getKey(),
            'title' => 'Live now',
            'status' => LiveSessionStatus::Live,
            'started_at' => now(),
        ]);

        $this->approvedClassroom($host, [
            'title' => 'Lớp không live',
            'visibility' => 'public',
        ]);

        $learner = User::factory()->create();
        $learner->assignRole(Role::Student->value);

        $this->actingAs($learner)
            ->get(route('classroom.index', ['filter' => 'live']))
            ->assertOk()
            ->assertSee('Lớp đang live')
            ->assertDontSee('Lớp không live');
    }

    public function test_member_sees_join_cta_on_live_classroom(): void
    {
        $host = User::factory()->create();
        $host->assignRole(Role::Instructor->value);

        $member = User::factory()->create();
        $member->assignRole(Role::Student->value);

        $classroom = $this->approvedClassroom($host, [
            'title' => 'Live member CTA',
            'visibility' => 'public',
        ]);

        $session = LiveSession::query()->create([
            'classroom_id' => $classroom->getKey(),
            'title' => 'Buổi live',
            'status' => LiveSessionStatus::Live,
            'started_at' => now(),
        ]);

        $this->actingAs($member)->post(route('classroom.join', $classroom));

        $this->actingAs($member)
            ->get(route('classroom.index'))
            ->assertOk()
            ->assertSee('Vào ngay')
            ->assertSee(route('classroom.live', [$classroom, $session]), false);
    }

    public function test_learner_classroom_detail_uses_semantic_content_structure(): void
    {
        $host = User::factory()->create(['name' => 'Giảng viên Minh']);
        $host->assignRole(Role::Instructor->value);
        $learner = User::factory()->create();
        $learner->assignRole(Role::Student->value);
        $classroom = $this->approvedClassroom($host, [
            'title' => 'Lớp ôn tập Tim mạch',
            'description' => 'Ôn tập kiến thức trọng tâm cùng giảng viên.',
            'visibility' => 'public',
        ]);
        LiveSession::query()->create([
            'classroom_id' => $classroom->getKey(),
            'title' => 'Buổi chữa đề số 1',
            'scheduled_at' => now()->addDay(),
            'status' => LiveSessionStatus::Scheduled,
        ]);

        $this->actingAs($learner)
            ->get(route('classroom.show', $classroom))
            ->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<h1', false)
            ->assertSee('aria-label="Điều hướng lớp học"', false)
            ->assertSee('id="live-sessions-heading"', false)
            ->assertSee('<ol', false)
            ->assertSee('<article', false)
            ->assertSee('<time datetime=', false)
            ->assertSee('id="members-heading"', false);
    }


    /** @param  array<string, mixed>  $data */
    private function approvedClassroom(User $host, array $data): Classroom
    {
        $classroom = app(CreateClassroomAction::class)->handle($host, $data);
        $classroom->update(['status' => ClassroomStatus::Active]);

        return $classroom->fresh() ?? $classroom;
    }
}
