<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Actions\ApproveClassroomAction;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\Classroom\Actions\CreateClassroomAction;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionSession;
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

        $this->assertSame(ClassroomStatus::PendingApproval, $classroom->status);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSee('Lớp chữa Step 1', false)
            ->assertSee('Chữa từ feedback', false)
            ->assertSee('Vào lớp')
            ->assertSee(route('admin.classrooms.show', $classroom), false);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('admin.classrooms.show', $classroom))
            ->assertOk()
            ->assertSee('Lớp chữa Step 1')
            ->assertSee($host->name);

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

    public function test_admin_can_watch_instructor_live_without_joining_class(): void
    {
        $admin = $this->staffWith2fa(Role::Admin);
        $host = User::factory()->create(['name' => 'Giảng viên live']);
        $host->assignRole(Role::Instructor->value);

        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Lớp đang dạy',
            'purpose' => ClassroomPurpose::ExamReview->value,
        ]);

        $session = LiveSession::query()->create([
            'classroom_id' => $classroom->getKey(),
            'title' => 'Buổi chữa đề',
            'scheduled_at' => now()->subHour(),
            'started_at' => now()->subMinutes(10),
            'status' => LiveSessionStatus::Live,
            'livekit_room_name' => 'admin-watch-room',
        ]);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSee('Vào lớp')
            ->assertSee('Xem live')
            ->assertSee('Giảng viên live');

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('admin.classrooms.show', $classroom))
            ->assertOk()
            ->assertSee('Vào live đang dạy')
            ->assertSee('Buổi chữa đề');

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('admin.classrooms.live', [$classroom, $session]))
            ->assertOk()
            ->assertSee('Đang xem với tư cách quản trị')
            ->assertSee('Giảng viên live');

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->getJson(route('admin.classrooms.live.api.bootstrap', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.permissions.can_moderate', false)
            ->assertJsonPath('data.permissions.can_publish', false)
            ->assertJsonPath('data.session.chat_readonly', false)
            ->assertJsonPath('data.token.role', 'subscriber')
            ->assertJsonPath('data.urls.messages', route('admin.classrooms.live.api.messages', [$classroom, $session]));

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->postJson(route('admin.classrooms.live.api.messages', [$classroom, $session]), [
                'body' => 'Xin chào từ ban quản trị!',
                'type' => 'chat',
            ])
            ->assertCreated()
            ->assertJsonPath('data.message.body', 'Xin chào từ ban quản trị!');

        $this->assertDatabaseHas('live_session_messages', [
            'live_session_id' => $session->id,
            'user_id' => $admin->id,
            'body' => 'Xin chào từ ban quản trị!',
        ]);
    }

    public function test_admin_can_approve_pending_classroom(): void
    {
        $admin = $this->staffWith2fa(Role::Admin);
        $host = User::factory()->create();
        $host->assignRole(Role::Instructor->value);

        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Lớp chờ duyệt',
            'purpose' => ClassroomPurpose::ExamReview->value,
            'visibility' => 'public',
        ]);

        app(ApproveClassroomAction::class)->handle($admin, $classroom);

        $this->assertSame(ClassroomStatus::Active, $classroom->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'classroom.approve',
        ]);
    }

    public function test_admin_can_create_classroom_with_premium_question_content(): void
    {
        $admin = $this->staffWith2fa(Role::Admin);
        $host = User::factory()->create();
        $host->assignRole(Role::Instructor->value);
        $question = Question::factory()->create([
            'stem' => 'Câu premium do admin chọn để chữa',
            'is_free' => false,
            'difficulty' => Difficulty::Hard,
        ]);
        Question::factory()->create([
            'stem' => 'Câu dễ không thuộc kết quả lọc',
            'difficulty' => Difficulty::Easy,
        ]);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('admin.classrooms.create'))
            ->assertOk()
            ->assertSee('Ngân hàng câu hỏi')
            ->assertSee('Bài thi Exam')
            ->assertSee('Câu cần chữa feedback')
            ->assertSee('Tất cả chủ đề lâm sàng')
            ->assertSee('Tất cả phân loại y khoa')
            ->assertSee('Tất cả độ khó')
            ->assertSee('Tạo lớp và nạp nội dung');

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->getJson(route('admin.classrooms.content.questions', ['q' => 'premium']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $question->id);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->getJson(route('admin.classrooms.content.questions', ['difficulty' => Difficulty::Hard->value]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $question->id)
            ->assertJsonPath('data.0.difficulty', Difficulty::Hard->label());

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->post(route('admin.classrooms.store'), [
                'host_user_id' => $host->id,
                'title' => 'Lớp có nội dung sẵn',
                'description' => 'Admin chuẩn bị nội dung trước cho giảng viên.',
                'purpose' => ClassroomPurpose::FeedbackReview->value,
                'visibility' => 'public',
                'content_source' => 'questions',
                'session_title' => 'Buổi chữa câu premium',
                'expected_duration_minutes' => 60,
                'question_ids' => [$question->id],
            ])
            ->assertRedirect();

        $classroom = Classroom::query()->where('title', 'Lớp có nội dung sẵn')->firstOrFail();
        $session = $classroom->sessions()->firstOrFail();

        $this->assertSame(ClassroomStatus::Active, $classroom->status);
        $this->assertSame('manual', $session->question_set['source']);
        $this->assertSame([$question->id], $session->questionIds());

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->get(route('admin.classrooms.show', $classroom))
            ->assertOk()
            ->assertSee('1 câu')
            ->assertSee('Ngân hàng câu hỏi');
    }

    public function test_admin_can_create_classroom_from_published_exam(): void
    {
        $admin = $this->staffWith2fa(Role::SuperAdmin);
        $host = User::factory()->create();
        $host->assignRole(Role::Instructor->value);
        $question = Question::factory()->create();
        $exam = Exam::query()->create([
            'title' => 'Exam Nội khoa tổng hợp',
            'duration_minutes' => 90,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);
        $exam->questions()->attach($question->id, ['order' => 1]);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->post(route('admin.classrooms.store'), [
                'host_user_id' => $host->id,
                'title' => 'Lớp chữa Exam',
                'purpose' => ClassroomPurpose::FeedbackReview->value,
                'visibility' => 'unlisted',
                'content_source' => 'exam',
                'session_title' => 'Chữa Exam Nội khoa',
                'exam_id' => $exam->id,
            ])
            ->assertRedirect();

        $classroom = Classroom::query()->where('title', 'Lớp chữa Exam')->firstOrFail();
        $session = $classroom->sessions()->firstOrFail();

        $this->assertSame(ClassroomPurpose::ExamReview, $classroom->purpose);
        $this->assertSame($exam->id, $session->linked_exam_id);
        $this->assertSame('exam', $session->question_set['source']);
        $this->assertSame([$question->id], $session->questionIds());
    }

    public function test_admin_can_load_feedback_questions_and_create_review_classroom(): void
    {
        $admin = $this->staffWith2fa(Role::Admin);
        $host = User::factory()->create();
        $host->assignRole(Role::Instructor->value);
        $student = User::factory()->create();
        $question = Question::factory()->create(['stem' => 'Câu hô hấp đang có feedback cần chữa']);
        $questionSession = QuestionSession::factory()->for($student)->create([
            'question_ids' => [$question->id],
        ]);
        QuestionFeedback::query()->create([
            'user_id' => $student->id,
            'question_id' => $question->id,
            'question_session_id' => $questionSession->id,
            'target' => 'question',
            'category' => 'incorrect',
            'message' => 'Nội dung cần được giảng viên giải thích lại.',
            'status' => QuestionFeedback::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->getJson(route('admin.classrooms.content.questions', ['source' => 'feedback']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $question->id)
            ->assertJsonPath('data.0.feedback_count', 1);

        $this->actingAs($admin)
            ->withSession([TwoFactorSession::KEY => now()->timestamp])
            ->post(route('admin.classrooms.store'), [
                'host_user_id' => $host->id,
                'title' => 'Lớp chữa feedback',
                'purpose' => ClassroomPurpose::FeedbackReview->value,
                'visibility' => 'public',
                'content_source' => 'feedback',
                'session_title' => 'Chữa feedback hô hấp',
                'question_ids' => [$question->id],
            ])
            ->assertRedirect();

        $session = Classroom::query()
            ->where('title', 'Lớp chữa feedback')
            ->firstOrFail()
            ->sessions()
            ->firstOrFail();

        $this->assertSame('feedback', $session->question_set['source']);
        $this->assertSame([$question->id], $session->questionIds());
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
