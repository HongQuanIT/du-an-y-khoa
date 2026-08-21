<?php

declare(strict_types=1);

namespace Modules\Classroom\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;
use Modules\Classroom\Models\LiveSession;
use Modules\QuestionBank\Models\Question;
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
        $this->assertSame(ClassroomStatus::PendingApproval, $classroom->status);

        $this->actingAs($instructor)
            ->get(route('teach.classes.show', $classroom))
            ->assertOk()
            ->assertSee('Chữa feedback Nội')
            ->assertSee('Câu đã chọn');
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

    public function test_instructor_cannot_attach_unpublished_question_to_live_session(): void
    {
        $instructor = $this->instructor(['email' => 'draft-question@example.com']);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::ExamReview);
        $draft = Question::factory()->draft()->free()->create([
            'stem' => 'Câu hỏi nháp không được gắn',
            'topic_id' => null,
        ]);

        $this->actingAs($instructor)
            ->getJson(route('teach.classes.questions.search', [$classroom, 'q' => 'không được gắn']))
            ->assertOk()
            ->assertJsonMissing(['id' => $draft->id]);

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.store', $classroom), [
                'title' => 'Buổi chứa câu nháp',
                'question_ids' => [$draft->id],
            ])
            ->assertSessionHasErrors('question_ids.0');

        $this->assertDatabaseMissing('live_sessions', ['title' => 'Buổi chứa câu nháp']);
    }

    public function test_instructor_can_schedule_start_and_end_live_from_teach_portal(): void
    {
        $instructor = $this->instructor();
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::ExamReview);
        $classroom->update(['status' => ClassroomStatus::Active]);
        $student = User::factory()->create(['email' => 'live-student@example.com']);
        $student->assignRole(Role::Student->value);
        ClassroomMember::query()->create([
            'classroom_id' => $classroom->id,
            'user_id' => $student->id,
            'role_in_class' => MemberRole::Member,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);
        $questions = Question::factory()
            ->count(2)
            ->free()
            ->withOptions()
            ->sequence(
                ['stem' => 'Câu hỏi live về tăng huyết áp?', 'topic_id' => null],
                ['stem' => 'Câu hỏi live về suy tim?', 'topic_id' => null],
            )
            ->create();

        $this->actingAs($instructor)
            ->getJson(route('teach.classes.questions.search', [$classroom, 'q' => 'tăng huyết áp']))
            ->assertOk()
            ->assertJsonPath('data.questions.0.id', $questions[0]->id);

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.store', $classroom), [
                'title' => 'Chữa đề Nội khoa',
                'scheduled_at' => now()->addHour()->format('Y-m-d H:i'),
                'question_ids' => $questions->pluck('id')->all(),
            ])
            ->assertRedirect(route('teach.classes.show', $classroom));

        $session = $classroom->sessions()->latest('id')->firstOrFail();
        $this->assertSame('Chữa đề Nội khoa', $session->title);
        $this->assertSame($questions->pluck('id')->all(), $session->questionIds());
        $premiumQuestion = Question::factory()->withOptions()->create([
            'stem' => '<p>Câu premium đã được host gắn hợp lệ?</p>',
            'topic_id' => null,
            'is_free' => false,
        ]);
        $session->update([
            'question_set' => [
                'source' => 'manual',
                'question_ids' => [...$questions->pluck('id')->all(), $premiumQuestion->id],
            ],
            'current_question_index' => 2,
        ]);

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.start', [$classroom, $session]))
            ->assertRedirect(route('teach.classes.sessions.studio', [$classroom, $session]));
        $this->assertTrue($session->fresh()->status->isLive());

        $this->actingAs($instructor)
            ->get(route('teach.classes.sessions.studio', [$classroom, $session]))
            ->assertOk()
            ->assertSee('Live Studio')
            ->assertSee('data-live-room', false);

        $this->actingAs($instructor)
            ->getJson(route('teach.classes.sessions.studio.api.bootstrap', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.session.status', 'live')
            ->assertJsonPath('data.permissions.can_publish', true)
            ->assertJsonPath('data.permissions.can_publish_audio', true)
            ->assertJsonPath('data.permissions.can_publish_video', true)
            ->assertJsonPath('data.permissions.can_publish_screen', true)
            ->assertJsonPath('data.token.role', 'publisher')
            ->assertJsonPath('data.question_panel.total', 3)
            ->assertJsonPath('data.question_panel.question.id', $premiumQuestion->id)
            ->assertJsonPath('data.question_panel.question.stem', '<p>Câu premium đã được host gắn hợp lệ?</p>')
            ->assertJsonPath(
                'data.urls.messages',
                route('teach.classes.sessions.studio.api.messages', [$classroom, $session]),
            );

        $this->actingAs($instructor)
            ->postJson(route('teach.classes.sessions.studio.api.messages', [$classroom, $session]), [
                'body' => 'Host đã vào Studio',
                'type' => 'chat',
            ])
            ->assertCreated();

        $this->actingAs($student)
            ->getJson(route('classroom.live.api.bootstrap', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.permissions.can_publish', true)
            ->assertJsonPath('data.permissions.can_publish_audio', true)
            ->assertJsonPath('data.permissions.can_publish_video', false)
            ->assertJsonPath('data.permissions.can_publish_screen', false)
            ->assertJsonPath('data.token.role', 'speaker')
            ->assertJsonPath('data.token.can_publish_audio', true)
            ->assertJsonPath('data.token.can_publish_video', false)
            ->assertJsonPath('data.token.can_publish_screen', false)
            ->assertJsonFragment(['body' => 'Host đã vào Studio'])
            ->assertJsonPath('data.question_panel.total', 3)
            ->assertJsonPath('data.question_panel.question.id', $premiumQuestion->id);

        $this->actingAs($student)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-live-session.'.$session->uuid,
            ])
            ->assertOk();

        $this->actingAs($instructor)
            ->postJson(route('teach.classes.sessions.studio.api.mute-chat', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.chat_muted', true);

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.end', [$classroom, $session]))
            ->assertRedirect(route('teach.classes.show', $classroom));
        $this->assertSame(LiveSessionStatus::Ended, $session->fresh()->status);

        $this->actingAs($instructor)
            ->get(route('teach.classes.sessions.studio', [$classroom, $session]))
            ->assertRedirect(route('teach.classes.show', $classroom));
    }

    public function test_instructor_can_start_live_before_classroom_approval(): void
    {
        $instructor = $this->instructor(['email' => 'pending-live@example.com']);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::FeedbackReview);
        $session = LiveSession::query()->create([
            'classroom_id' => $classroom->id,
            'title' => 'Test trước duyệt',
            'status' => LiveSessionStatus::Scheduled,
            'scheduled_at' => now(),
        ]);

        $this->actingAs($instructor)
            ->get(route('teach.classes.show', $classroom))
            ->assertOk()
            ->assertSee('Test trước duyệt')
            ->assertSee(route('teach.classes.sessions.start', [$classroom, $session]), false);

        $this->actingAs($instructor)
            ->get(route('teach.classes.sessions.studio', [$classroom, $session]))
            ->assertOk()
            ->assertSee('Bắt đầu live')
            ->assertSee('data-lk-root', false);

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.start', [$classroom, $session]))
            ->assertRedirect(route('teach.classes.sessions.studio', [$classroom, $session]));

        $this->assertSame(LiveSessionStatus::Live, $session->fresh()->status);
    }

    public function test_instructor_cannot_manage_live_for_foreign_teach_classroom(): void
    {
        $owner = $this->instructor(['email' => 'live-owner@example.com']);
        $other = $this->instructor(['email' => 'live-other@example.com']);
        $classroom = $this->seedClassroom($owner, ClassroomPurpose::ExamReview);

        $this->actingAs($other)
            ->post(route('teach.classes.sessions.store', $classroom), ['title' => 'Không được phép'])
            ->assertForbidden();

        $this->assertDatabaseMissing('live_sessions', ['title' => 'Không được phép']);
    }

    public function test_instructor_can_delete_own_teach_classroom(): void
    {
        $instructor = $this->instructor(['email' => 'delete-owner@example.com']);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::ExamReview);

        $this->actingAs($instructor)
            ->delete(route('teach.classes.destroy', $classroom))
            ->assertRedirect(route('teach.classes.index'))
            ->assertSessionHas('status', 'Đã xoá lớp: '.$classroom->title);

        $this->assertSoftDeleted('classrooms', [
            'id' => $classroom->id,
        ]);
    }

    public function test_instructor_cannot_delete_live_or_foreign_teach_classroom(): void
    {
        $owner = $this->instructor(['email' => 'delete-live-owner@example.com']);
        $other = $this->instructor(['email' => 'delete-live-other@example.com']);
        $classroom = $this->seedClassroom($owner, ClassroomPurpose::FeedbackReview);

        $this->actingAs($other)
            ->delete(route('teach.classes.destroy', $classroom))
            ->assertForbidden();

        $session = LiveSession::query()->create([
            'classroom_id' => $classroom->id,
            'title' => 'Live không xoá',
            'status' => LiveSessionStatus::Live,
            'started_at' => now(),
            'livekit_room_name' => 'classroom-test-live-delete',
        ]);

        $this->actingAs($owner)
            ->delete(route('teach.classes.destroy', $classroom))
            ->assertStatus(409);

        $this->assertDatabaseHas('classrooms', [
            'id' => $classroom->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('live_sessions', [
            'id' => $session->id,
            'status' => LiveSessionStatus::Live->value,
        ]);
    }

    public function test_instructor_can_kick_active_student_from_teach_classroom(): void
    {
        $instructor = $this->instructor(['email' => 'kick-owner@example.com']);
        $student = User::factory()->create(['email' => 'kick-student@example.com']);
        $student->assignRole(Role::Student->value);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::ExamReview);

        ClassroomMember::query()->create([
            'classroom_id' => $classroom->id,
            'user_id' => $student->id,
            'role_in_class' => MemberRole::Member,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);

        LiveSession::query()->create([
            'classroom_id' => $classroom->id,
            'title' => 'Live đang chạy',
            'status' => LiveSessionStatus::Live,
            'started_at' => now(),
            'livekit_room_name' => 'classroom-test-kick',
        ]);

        $this->actingAs($instructor)
            ->postJson(route('teach.classes.members.kick', [$classroom, $student]))
            ->assertOk()
            ->assertJsonPath('data.kicked', true);

        $this->assertDatabaseHas('classroom_members', [
            'classroom_id' => $classroom->id,
            'user_id' => $student->id,
            'status' => MemberStatus::Active->value,
        ]);

        $this->actingAs($student)
            ->get(route('classroom.show', $classroom))
            ->assertOk();
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
