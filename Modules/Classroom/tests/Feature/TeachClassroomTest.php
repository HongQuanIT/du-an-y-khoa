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
use Modules\Notification\Models\UserNotification;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\Tag;
use Modules\Search\Models\SearchDocument;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class TeachClassroomTest extends TestCase
{
    use CreatesMedicalTaxonomy;
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
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $this->actingAs($instructor)
            ->get(route('teach.classes.index'))
            ->assertOk()
            ->assertSee('Lớp của tôi')
            ->assertSee('Tạo lớp')
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertSee('name="description" content="Quản lý lớp chữa đề, lịch phát trực tiếp và học viên trên cổng giảng viên."', false)
            ->assertSee('aria-labelledby="classroom-list-title"', false);

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
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => 'classroom.pending_approval',
            'title' => 'Có lớp học mới chờ duyệt',
            'action_url' => route('admin.classrooms.show', $classroom),
        ]);
        $this->assertStringContainsString(
            $instructor->name,
            UserNotification::query()->where('user_id', $admin->id)->firstOrFail()->body,
        );

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
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::FeedbackReview);
        $draft = Question::factory()->draft()->free()->create([
            'stem' => 'Câu hỏi nháp không được gắn',
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

    public function test_instructor_can_filter_live_library_by_topic_and_feedback_category(): void
    {
        $instructor = $this->instructor(['email' => 'filter-library@example.com']);
        $student = User::factory()->create([
            'name' => 'Học viên gửi feedback',
            'email' => 'feedback-student@example.com',
        ]);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::FeedbackReview);
        $blueprint = Blueprint::query()->create([
            'name' => 'Blueprint lọc live',
            'slug' => 'blueprint-loc-live',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $section = BlueprintSection::query()->create([
            'blueprint_id' => $blueprint->getKey(),
            'name' => 'Nội khoa',
            'slug' => 'noi-khoa',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $coreTopic = CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $section->getKey(),
            'name' => 'Đau ngực',
            'slug' => 'dau-nguc',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $cardiology = $this->makeMedicalNode(['name' => 'Tim mạch']);
        $respiratory = $this->makeMedicalNode(['name' => 'Hô hấp']);
        $tag = Tag::query()->create([
            'name' => 'ECG',
            'slug' => 'ecg',
            'status' => TaxonomyStatus::Active,
        ]);
        $cardiologyQuestion = Question::factory()->free()->withOptions()->create([
            'stem' => 'Câu hỏi lọc theo Tim mạch',
        ]);
        $respiratoryQuestion = Question::factory()->free()->withOptions()->create([
            'stem' => 'Câu hỏi lọc theo Hô hấp',
        ]);
        $cardiologyQuestion->coreClinicalTopics()->attach($coreTopic->getKey());
        $cardiologyQuestion->medicalTaxonomyNodes()->attach($cardiology->getKey());
        $cardiologyQuestion->tags()->attach($tag->getKey());
        $respiratoryQuestion->medicalTaxonomyNodes()->attach($respiratory->getKey());
        $questionSession = QuestionSession::factory()->for($student)->create([
            'question_ids' => [$respiratoryQuestion->getKey()],
        ]);
        QuestionFeedback::query()->create([
            'user_id' => $student->getKey(),
            'question_id' => $respiratoryQuestion->getKey(),
            'question_session_id' => $questionSession->getKey(),
            'target' => 'question',
            'category' => 'incorrect',
            'message' => 'Nội dung cần kiểm tra lại.',
        ]);

        $this->actingAs($instructor)
            ->get(route('teach.classes.show', $classroom))
            ->assertOk()
            ->assertSee('Feedback học viên')
            ->assertSee('@click.stop="openFeedback(question)"', false)
            ->assertSee('Chi tiết feedback');

        $this->actingAs($instructor)
            ->getJson(route('teach.classes.questions.search', [
                $classroom,
                'medical_taxonomy_node_ids' => [$cardiology->getKey()],
            ]))
            ->assertOk()
            ->assertJsonPath('data.questions.0.id', $cardiologyQuestion->id)
            ->assertJsonPath('data.questions.0.core_topics.0.name', 'Đau ngực')
            ->assertJsonMissing(['id' => $respiratoryQuestion->id]);

        $this->actingAs($instructor)
            ->getJson(route('teach.classes.questions.search', [
                $classroom,
                'core_clinical_topic_ids' => [$coreTopic->getKey()],
            ]))
            ->assertOk()
            ->assertJsonPath('data.questions.0.id', $cardiologyQuestion->id)
            ->assertJsonMissing(['id' => $respiratoryQuestion->id]);

        $this->actingAs($instructor)
            ->getJson(route('teach.classes.questions.search', [
                $classroom,
                'tag_ids' => [$tag->getKey()],
            ]))
            ->assertOk()
            ->assertJsonPath('data.questions.0.id', $cardiologyQuestion->id)
            ->assertJsonMissing(['id' => $respiratoryQuestion->id]);

        $this->actingAs($instructor)
            ->getJson(route('teach.classes.questions.search', [
                $classroom,
                'feedback_categories' => ['incorrect'],
            ]))
            ->assertOk()
            ->assertJsonPath('data.questions.0.id', $respiratoryQuestion->id)
            ->assertJsonMissing(['id' => $cardiologyQuestion->id]);

        $this->actingAs($instructor)
            ->getJson(route('teach.classes.questions.feedback', [$classroom, $respiratoryQuestion]))
            ->assertOk()
            ->assertJsonPath('data.question.id', $respiratoryQuestion->id)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.feedback.0.student', 'Học viên gửi feedback')
            ->assertJsonPath('data.feedback.0.category', 'Nội dung không chính xác')
            ->assertJsonPath('data.feedback.0.message', 'Nội dung cần kiểm tra lại.');
    }

    public function test_instructor_can_schedule_start_and_end_live_from_teach_portal(): void
    {
        $instructor = $this->instructor();
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::FeedbackReview);
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
                ['stem' => 'Câu hỏi live về tăng huyết áp?'],
                ['stem' => 'Câu hỏi live về suy tim?'],
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

        $this->actingAs($instructor)
            ->get(route('teach.classes.show', $classroom))
            ->assertOk()
            ->assertSee('Mở lại buổi live')
            ->assertSee(route('teach.classes.sessions.start', [$classroom, $session]), false);

        $originalStartedAt = $session->fresh()->started_at;

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.start', [$classroom, $session]))
            ->assertRedirect(route('teach.classes.sessions.studio', [$classroom, $session]));

        $reopened = $session->fresh();
        $this->assertSame(LiveSessionStatus::Live, $reopened->status);
        $this->assertNull($reopened->ended_at);
        $this->assertTrue($reopened->started_at?->equalTo($originalStartedAt) ?? false);
        $this->assertDatabaseHas('live_session_messages', [
            'live_session_id' => $session->id,
            'body' => 'Buổi live đã được mở lại.',
        ]);

        $this->actingAs($instructor)
            ->get(route('teach.classes.sessions.studio', [$classroom, $session]))
            ->assertOk()
            ->assertSee('Live Studio');
    }

    public function test_instructor_can_schedule_a_live_session_with_more_than_fifty_questions(): void
    {
        $instructor = $this->instructor();
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::FeedbackReview);
        $classroom->update(['status' => ClassroomStatus::Active]);
        $questions = Question::factory()->count(51)->free()->withOptions()->create();

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.store', $classroom), [
                'title' => 'Chữa bộ đề đầy đủ',
                'question_ids' => $questions->pluck('id')->all(),
            ])
            ->assertRedirect(route('teach.classes.show', $classroom))
            ->assertSessionHasNoErrors();

        $session = $classroom->sessions()->latest('id')->firstOrFail();

        $this->assertCount(51, $session->questionIds());
        $this->assertSame($questions->pluck('id')->all(), $session->questionIds());
    }

    public function test_exam_review_session_uses_all_questions_from_the_selected_exam_in_order(): void
    {
        $instructor = $this->instructor(['email' => 'exam-review@example.com']);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::ExamReview);
        $classroom->update(['status' => ClassroomStatus::Active]);
        $questions = Question::factory()->count(3)->free()->withOptions()->create();
        $exam = Exam::query()->create([
            'title' => 'Đề thi Nội khoa 2026',
            'description' => 'Đề thi dùng cho buổi chữa đề.',
            'duration_minutes' => 90,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);
        $orderedIds = [$questions[2]->id, $questions[0]->id, $questions[1]->id];
        $exam->questions()->attach([
            $orderedIds[0] => ['order' => 1],
            $orderedIds[1] => ['order' => 2],
            $orderedIds[2] => ['order' => 3],
        ]);

        $this->actingAs($instructor)
            ->get(route('teach.classes.show', $classroom))
            ->assertOk()
            ->assertSee('Chọn đề thi')
            ->assertSee('Đề thi Nội khoa 2026')
            ->assertDontSee('Thư viện câu hỏi');

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.store', $classroom), [
                'title' => 'Chữa đề Nội khoa 2026',
                'exam_id' => $exam->getKey(),
            ])
            ->assertRedirect(route('teach.classes.show', $classroom))
            ->assertSessionHasNoErrors();

        $session = $classroom->sessions()->latest('id')->firstOrFail();

        $this->assertSame($exam->getKey(), $session->linked_exam_id);
        $this->assertSame('exam', $session->question_set['source']);
        $this->assertSame($orderedIds, $session->questionIds());
    }

    public function test_exam_review_session_requires_a_published_exam_instead_of_manual_questions(): void
    {
        $instructor = $this->instructor(['email' => 'exam-required@example.com']);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::ExamReview);
        $question = Question::factory()->free()->withOptions()->create();

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.store', $classroom), [
                'title' => 'Chữa đề thiếu đề thi',
                'question_ids' => [$question->id],
            ])
            ->assertSessionHasErrors(['exam_id', 'question_ids']);

        $this->assertDatabaseMissing('live_sessions', ['title' => 'Chữa đề thiếu đề thi']);
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

    public function test_instructor_can_close_active_classroom_after_live_has_ended(): void
    {
        $instructor = $this->instructor(['email' => 'close-classroom@example.com']);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::FeedbackReview);
        $classroom->update(['status' => ClassroomStatus::Active]);

        $session = LiveSession::query()->create([
            'classroom_id' => $classroom->id,
            'title' => 'Buổi live đã hoàn thành',
            'status' => LiveSessionStatus::Ended,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
        ]);

        $this->actingAs($instructor)
            ->get(route('teach.classes.show', $classroom))
            ->assertOk()
            ->assertSee('Đóng lớp')
            ->assertSee(route('teach.classes.close', $classroom), false);

        $this->actingAs($instructor)
            ->post(route('teach.classes.close', $classroom))
            ->assertRedirect(route('teach.classes.show', $classroom));

        $this->assertSame(ClassroomStatus::Closed, $classroom->fresh()->status);

        $this->actingAs($instructor)
            ->get(route('teach.classes.show', $classroom))
            ->assertOk()
            ->assertSee('Lớp đã đóng')
            ->assertDontSee(route('teach.classes.sessions.start', [$classroom, $session]), false);

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.start', [$classroom, $session]))
            ->assertStatus(409);

        $this->actingAs($instructor)
            ->post(route('teach.classes.sessions.store', $classroom), [
                'title' => 'Không được lên lịch thêm',
            ])
            ->assertStatus(409);
    }

    public function test_instructor_can_reopen_previously_approved_closed_classroom(): void
    {
        config(['audit.queue_enabled' => false]);
        $instructor = $this->instructor(['email' => 'reopen-classroom@example.com']);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::FeedbackReview);
        $classroom->update([
            'status' => ClassroomStatus::Active,
            'visibility' => ClassroomVisibility::Public,
        ]);
        $searchDocumentId = SearchDocument::query()
            ->where('source_type', Classroom::class)
            ->where('source_id', $classroom->getKey())
            ->value('id');
        $this->assertNotNull($searchDocumentId);

        $classroom->update(['status' => ClassroomStatus::Closed]);
        $this->assertSoftDeleted('search_documents', ['id' => $searchDocumentId]);

        $this->actingAs($instructor)
            ->get(route('teach.classes.show', $classroom))
            ->assertOk()
            ->assertSee('Mở lại lớp')
            ->assertSee(route('teach.classes.reopen', $classroom), false);

        $this->actingAs($instructor)
            ->post(route('teach.classes.reopen', $classroom))
            ->assertRedirect(route('teach.classes.show', $classroom));

        $this->assertSame(ClassroomStatus::Active, $classroom->fresh()->status);
        $this->assertDatabaseHas('search_documents', [
            'id' => $searchDocumentId,
            'source_type' => Classroom::class,
            'source_id' => $classroom->getKey(),
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'classroom.reopened',
            'actor_id' => $instructor->id,
        ]);
    }

    public function test_significant_edit_requires_admin_approval_again(): void
    {
        config(['audit.queue_enabled' => false]);
        $instructor = $this->instructor(['email' => 'edit-approved-classroom@example.com']);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::FeedbackReview);
        $classroom->update(['status' => ClassroomStatus::Active]);

        $this->actingAs($instructor)
            ->put(route('teach.classes.update', $classroom), [
                'title' => 'Tên lớp đã thay đổi đáng kể',
                'description' => $classroom->description,
                'purpose' => $classroom->purpose->value,
                'visibility' => $classroom->visibility->value,
                'max_members' => $classroom->max_members,
            ])
            ->assertRedirect(route('teach.classes.show', $classroom));

        $classroom->refresh();
        $this->assertSame('Tên lớp đã thay đổi đáng kể', $classroom->title);
        $this->assertSame(ClassroomStatus::PendingApproval, $classroom->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'classroom.updated',
            'actor_id' => $instructor->id,
        ]);
    }

    public function test_operational_edit_keeps_existing_approval_status(): void
    {
        $instructor = $this->instructor(['email' => 'resize-approved-classroom@example.com']);
        $classroom = $this->seedClassroom($instructor, ClassroomPurpose::ExamReview);
        $classroom->update(['status' => ClassroomStatus::Active, 'max_members' => 50]);

        $this->actingAs($instructor)
            ->put(route('teach.classes.update', $classroom), [
                'title' => $classroom->title,
                'description' => $classroom->description,
                'purpose' => $classroom->purpose->value,
                'visibility' => $classroom->visibility->value,
                'max_members' => 80,
            ])
            ->assertRedirect(route('teach.classes.show', $classroom));

        $classroom->refresh();
        $this->assertSame(80, $classroom->max_members);
        $this->assertSame(ClassroomStatus::Active, $classroom->status);
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
