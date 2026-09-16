<?php

declare(strict_types=1);

namespace Modules\Classroom\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Actions\CaptureQuestionVersionAction;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewRequest;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class TeachQuestionReviewTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private Lesson $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->topic = $this->makeLesson([
            'name' => 'Nội tiết',
            'slug' => 'noi-tiet-teach-review',
            'sort_order' => 1,
        ]);
    }

    public function test_instructor_can_list_questions_awaiting_review(): void
    {
        $instructor = $this->instructor();
        $pending = $this->makeInReviewQuestion($instructor);
        $other = $this->makeInReviewQuestion();
        $other->forceFill(['stem' => 'Câu gán giảng viên khác không hiện trong hàng đợi của tôi'])->save();
        $draft = $this->makeDraftQuestion();

        $this->actingAs($instructor)
            ->get(route('teach.questions.reviews.index'))
            ->assertOk()
            ->assertSee('Danh sách duyệt câu hỏi')
            ->assertSee('Chờ duyệt')
            ->assertSee('Đã duyệt')
            ->assertSee('Đã từ chối')
            ->assertSee(strip_tags((string) $pending->stem))
            ->assertDontSee(strip_tags((string) $other->stem))
            ->assertDontSee(strip_tags((string) $draft->stem));
    }

    public function test_instructor_can_view_approved_and_rejected_tabs(): void
    {
        $instructor = $this->instructor();
        $approved = $this->makeInReviewQuestion($instructor);
        $approved->forceFill([
            'status' => QuestionStatus::PendingPublish,
            'instructor_id' => $instructor->id,
            'assigned_instructor_id' => $instructor->id,
            'instructor_decision' => 'approved',
        ])->save();

        $rejected = $this->makeInReviewQuestion($instructor);
        $rejected->forceFill([
            'status' => QuestionStatus::Rejected,
            'instructor_id' => $instructor->id,
            'assigned_instructor_id' => $instructor->id,
            'instructor_decision' => 'rejected',
            'rejected_by_role' => Role::Instructor->value,
            'rejection_reason' => 'Cần sửa stem',
            'stem' => 'Câu đã từ chối riêng biệt để assert',
        ])->save();

        $this->actingAs($instructor)
            ->get(route('teach.questions.reviews.index', ['tab' => 'approved']))
            ->assertOk()
            ->assertSee(strip_tags((string) $approved->stem));

        $this->actingAs($instructor)
            ->get(route('teach.questions.reviews.index', ['tab' => 'rejected']))
            ->assertOk()
            ->assertSee('Câu đã từ chối riêng biệt để assert')
            ->assertSee('Cần sửa stem');
    }

    public function test_instructor_can_approve_without_bumping_version(): void
    {
        $instructor = $this->instructor();
        $question = $this->makeInReviewQuestion($instructor);
        $versionBefore = (int) $question->version;

        $this->actingAs($instructor)
            ->post(route('teach.questions.reviews.approve', $question), [
                'review_note' => 'Nội dung ổn.',
            ])
            ->assertRedirect(route('teach.questions.reviews.index', ['tab' => 'approved']));

        $question->refresh();

        $this->assertSame(QuestionStatus::InFlagReview, $question->status);
        $this->assertSame($versionBefore, (int) $question->version);
        $this->assertSame($instructor->id, (int) $question->instructor_id);
        $this->assertSame('approved', $question->instructor_decision);
        $this->assertNull($question->rejection_reason);
        $this->assertDatabaseHas('question_instructor_reviews', [
            'question_id' => $question->id,
            'instructor_id' => $instructor->id,
            'decision' => 'approved',
        ]);
        $this->assertDatabaseHas('question_review_requests', [
            'question_id' => $question->id,
            'status' => QuestionReviewStatus::Approved->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'teach.question.instructor_approved',
        ]);
    }

    public function test_instructor_can_reject_with_reason(): void
    {
        $instructor = $this->instructor();
        $question = $this->makeInReviewQuestion($instructor);
        $versionBefore = (int) $question->version;

        $this->actingAs($instructor)
            ->post(route('teach.questions.reviews.reject', $question), [
                'review_note' => 'Thiếu giải thích đáp án nhiễu.',
            ])
            ->assertRedirect(route('teach.questions.reviews.index', ['tab' => 'rejected']));

        $question->refresh();

        $this->assertSame(QuestionStatus::Rejected, $question->status);
        $this->assertSame($versionBefore, (int) $question->version);
        $this->assertSame('Thiếu giải thích đáp án nhiễu.', $question->rejection_reason);
        $this->assertSame(Role::Instructor->value, $question->rejected_by_role);
        $this->assertSame($instructor->id, (int) $question->instructor_id);
        $this->assertSame('rejected', $question->instructor_decision);
        $this->assertDatabaseHas('question_review_requests', [
            'question_id' => $question->id,
            'status' => QuestionReviewStatus::Rejected->value,
        ]);
        $this->assertDatabaseHas('question_instructor_reviews', [
            'question_id' => $question->id,
            'instructor_id' => $instructor->id,
            'decision' => 'rejected',
        ]);
    }

    public function test_reject_requires_reason(): void
    {
        $instructor = $this->instructor();
        $question = $this->makeInReviewQuestion($instructor);

        $this->actingAs($instructor)
            ->from(route('teach.questions.reviews.show', $question))
            ->post(route('teach.questions.reviews.reject', $question), [
                'review_note' => '',
            ])
            ->assertRedirect(route('teach.questions.reviews.show', $question))
            ->assertSessionHasErrors('review_note');

        $this->assertSame(QuestionStatus::InReview, $question->fresh()->status);
    }

    public function test_review_detail_compares_working_copy_with_published_snapshot(): void
    {
        $instructor = $this->instructor();
        $question = $this->makePublishedThenInReviewQuestion($instructor);

        $this->actingAs($instructor)
            ->get(route('teach.questions.reviews.show', $question))
            ->assertOk()
            ->assertSee('So sánh với bản đang xuất bản', false)
            ->assertSee('Bản đang xuất bản', false)
            ->assertSee('Bản cần duyệt', false)
            ->assertSee('Bệnh nhân sốt', false)
            ->assertSee('>cao<', false)
            ->assertSee('>nhẹ<', false)
            ->assertSee('>3<', false)
            ->assertSee('>5<', false)
            ->assertSee('Xóa', false)
            ->assertSee('Sửa', false)
            ->assertSee('Thêm', false)
            ->assertDontSee('Chưa có phiên bản đang xuất bản để so sánh', false);
    }

    public function test_new_question_review_shows_empty_published_pane(): void
    {
        $instructor = $this->instructor();
        $question = $this->makeInReviewQuestion($instructor);

        $this->actingAs($instructor)
            ->get(route('teach.questions.reviews.show', $question))
            ->assertOk()
            ->assertSee('So sánh với bản đang xuất bản', false)
            ->assertSee('Bản đang xuất bản', false)
            ->assertSee('Bản cần duyệt', false)
            ->assertSee('Chưa có phiên bản đang xuất bản để so sánh', false)
            ->assertSee(strip_tags((string) $question->stem), false)
            ->assertDontSee('Giải thích chung', false)
            ->assertDontSee('Chưa nhập.', false)
            ->assertSee('Ý chính cần ghi nhớ', false)
            ->assertSee('Kiến thức / Gợi ý', false);
    }

    public function test_review_detail_preserves_stem_html_and_hides_empty_key_info(): void
    {
        $instructor = $this->instructor();
        $question = $this->makeInReviewQuestion($instructor);
        $question->forceFill([
            'stem' => '<p>Bệnh nhân <strong>sốt cao</strong>.</p><ul><li>Troponin tăng</li></ul>',
            'attending_tip' => '<p>Nhớ <em>cấy máu</em> trước kháng sinh.</p>',
            'key_info' => ['Đau ngực kiểu mạch vành'],
        ])->save();

        $this->actingAs($instructor)
            ->get(route('teach.questions.reviews.show', $question->fresh()))
            ->assertOk()
            ->assertSee('Câu hỏi', false)
            ->assertDontSee('>Đề bài<', false)
            ->assertSee('<strong>sốt cao</strong>', false)
            ->assertSee('<li>Troponin tăng</li>', false)
            ->assertSee('<em>cấy máu</em>', false)
            ->assertSee('Đau ngực kiểu mạch vành', false)
            ->assertSee('Ý chính cần ghi nhớ', false)
            ->assertSee('Kiến thức / Gợi ý', false)
            ->assertDontSee('Giải thích chung', false);
    }

    public function test_student_cannot_access_review_queue(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);
        $this->makeInReviewQuestion();

        $this->actingAs($student)
            ->get(route('teach.questions.reviews.index'))
            ->assertRedirect();
    }

    private function instructor(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Instructor->value);

        return $user;
    }

    private function makeDraftQuestion(): Question
    {
        $question = Question::factory()->draft()->create([
            'stem' => 'Stem nháp không hiện trong hàng đợi',
            'explanation' => 'Explanation draft',
            'difficulty' => Difficulty::Easy,
        ]);
        $question->lessons()->sync([$this->topic->id]);

        return $question;
    }

    private function makeInReviewQuestion(?User $assigned = null): Question
    {
        $creator = User::factory()->create();
        $creator->assignRole(Role::ContentEditor->value);

        $question = Question::factory()->create([
            'stem' => 'Bệnh nhân sốt cao 3 ngày. Chẩn đoán phù hợp?',
            'explanation' => 'Giải thích lâm sàng đầy đủ cho hàng đợi giảng viên.',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::InReview,
            'created_by' => $creator->id,
            'assigned_instructor_id' => $assigned?->id,
            'version' => 0,
        ]);
        $question->lessons()->sync([$this->topic->id]);

        foreach (['Virus', 'Vi khuẩn', 'Nấm', 'Ký sinh'] as $i => $content) {
            $question->options()->create([
                'label' => chr(65 + $i),
                'content' => $content,
                'is_correct' => $i === 0,
                'explanation' => $i === 0 ? 'Đúng' : 'Sai',
                'order' => $i + 1,
            ]);
        }

        QuestionReviewRequest::query()->create([
            'question_id' => $question->id,
            'action' => QuestionReviewAction::Create,
            'status' => QuestionReviewStatus::Pending,
            'requested_by' => $creator->id,
        ]);

        return $question->fresh(['options', 'lessons']);
    }

    private function makePublishedThenInReviewQuestion(?User $assigned = null): Question
    {
        $creator = User::factory()->create();
        $creator->assignRole(Role::ContentEditor->value);

        $question = Question::factory()->create([
            'stem' => 'Bệnh nhân sốt cao 3 ngày. Chẩn đoán phù hợp?',
            'explanation' => 'Giải thích bản xuất bản.',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::Published,
            'created_by' => $creator->id,
            'version' => 1,
            'published_version' => 1,
        ]);
        $question->lessons()->sync([$this->topic->id]);

        foreach (['Virus', 'Vi khuẩn', 'Nấm', 'Ký sinh'] as $i => $content) {
            $question->options()->create([
                'label' => chr(65 + $i),
                'content' => $content,
                'is_correct' => $i === 0,
                'explanation' => $i === 0 ? 'Đúng' : 'Sai',
                'order' => $i + 1,
            ]);
        }

        $question = $question->fresh(['options', 'lessons']);
        app(CaptureQuestionVersionAction::class)->handle($question, $creator, 'publish');

        $question->forceFill([
            'stem' => 'Bệnh nhân sốt nhẹ 5 ngày. Chẩn đoán phù hợp?',
            'explanation' => 'Giải thích bản gửi duyệt.',
            'status' => QuestionStatus::InReview,
            'assigned_instructor_id' => $assigned?->id,
        ])->save();

        QuestionReviewRequest::query()->create([
            'question_id' => $question->id,
            'action' => QuestionReviewAction::Update,
            'status' => QuestionReviewStatus::Pending,
            'requested_by' => $creator->id,
        ]);

        return $question->fresh(['options', 'lessons']);
    }

    public function test_instructor_permission_granularity_for_question_review(): void
    {
        $instructor = $this->instructor();
        $question = $this->makeInReviewQuestion();

        // 1. Instructor có đầy đủ quyền mặc định -> truy cập index và show bình thường
        $this->actingAs($instructor)
            ->get(route('teach.questions.reviews.index'))
            ->assertOk();

        $this->actingAs($instructor)
            ->get(route('teach.questions.reviews.show', $question))
            ->assertOk();

        // 2. Tạo role giảng viên bị hạn chế (không có quyền question nào)
        $restrictedRole = \Spatie\Permission\Models\Role::create([
            'name' => 'restricted_instructor',
            'guard_name' => 'web',
            'portal' => \App\Support\Enums\PortalGroup::Instructor->value,
        ]);
        $restrictedInstructor = User::factory()->create();
        $restrictedInstructor->assignRole($restrictedRole);

        $this->actingAs($restrictedInstructor)
            ->get(route('teach.questions.reviews.index'))
            ->assertForbidden();

        $this->actingAs($restrictedInstructor)
            ->get(route('teach.questions.reviews.show', $question))
            ->assertForbidden();

        // 3. Chỉ cấp question.view_any -> xem được index nhưng không xem được show và không duyệt được
        $restrictedRole->givePermissionTo('question.view_any');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($restrictedInstructor)
            ->get(route('teach.questions.reviews.index'))
            ->assertOk();

        $this->actingAs($restrictedInstructor)
            ->get(route('teach.questions.reviews.show', $question))
            ->assertForbidden();

        // 4. Cấp question.view -> xem được show nhưng không duyệt / không từ chối được (403)
        $restrictedRole->givePermissionTo(Permission::QuestionView->value);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($restrictedInstructor)
            ->get(route('teach.questions.reviews.show', $question))
            ->assertOk()
            ->assertDontSee('Duyệt chuyên môn')
            ->assertDontSee('Từ chối');

        $this->actingAs($restrictedInstructor)
            ->post(route('teach.questions.reviews.approve', $question), [
                'review_note' => 'Cố tình duyệt khi không có quyền approve',
            ])
            ->assertForbidden();

        $this->actingAs($restrictedInstructor)
            ->post(route('teach.questions.reviews.reject', $question), [
                'review_note' => 'Cố tình từ chối khi không có quyền reject',
            ])
            ->assertForbidden();

        // 5. Cấp question.approve -> thấy và bấm được Duyệt chuyên môn, nhưng chưa có quyền Từ chối
        $restrictedRole->givePermissionTo('question.approve');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($restrictedInstructor)
            ->get(route('teach.questions.reviews.show', $question))
            ->assertOk()
            ->assertSee('Duyệt chuyên môn')
            ->assertDontSee('Từ chối');

        $this->actingAs($restrictedInstructor)
            ->post(route('teach.questions.reviews.reject', $question), [
                'review_note' => 'Cố tình từ chối khi chưa có quyền reject',
            ])
            ->assertForbidden();

        // 6. Cấp question.reject -> thấy và bấm được cả Từ chối
        $restrictedRole->givePermissionTo('question.reject');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($restrictedInstructor)
            ->get(route('teach.questions.reviews.show', $question))
            ->assertOk()
            ->assertSee('Duyệt chuyên môn')
            ->assertSee('Từ chối');

        $this->actingAs($restrictedInstructor)
            ->post(route('teach.questions.reviews.reject', $question), [
                'review_note' => 'Đã có quyền reject hợp lệ',
            ])
            ->assertRedirect(route('teach.questions.reviews.index', ['tab' => 'rejected']));
    }
}
