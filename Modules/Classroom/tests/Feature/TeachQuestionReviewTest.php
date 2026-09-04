<?php

declare(strict_types=1);

namespace Modules\Classroom\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewRequest;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class TeachQuestionReviewTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private MedicalTaxonomyNode $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->topic = $this->makeMedicalNode([
            'name' => 'Nội tiết',
            'slug' => 'noi-tiet-teach-review',
            'node_type' => 'specialty',
            'sort_order' => 1,
        ]);
    }

    public function test_instructor_can_list_questions_awaiting_review(): void
    {
        $instructor = $this->instructor();
        $pending = $this->makeInReviewQuestion();
        $draft = $this->makeDraftQuestion();

        $this->actingAs($instructor)
            ->get(route('teach.questions.reviews.index'))
            ->assertOk()
            ->assertSee('Danh sách duyệt câu hỏi')
            ->assertSee('Chờ duyệt')
            ->assertSee('Đã duyệt')
            ->assertSee('Đã từ chối')
            ->assertSee(strip_tags((string) $pending->stem))
            ->assertDontSee(strip_tags((string) $draft->stem));
    }

    public function test_instructor_can_view_approved_and_rejected_tabs(): void
    {
        $instructor = $this->instructor();
        $approved = $this->makeInReviewQuestion();
        $approved->forceFill([
            'status' => QuestionStatus::PendingPublish,
            'instructor_id' => $instructor->id,
        ])->save();

        $rejected = $this->makeInReviewQuestion();
        $rejected->forceFill([
            'status' => QuestionStatus::Rejected,
            'instructor_id' => $instructor->id,
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
        $question = $this->makeInReviewQuestion();
        $versionBefore = (int) $question->version;

        $this->actingAs($instructor)
            ->post(route('teach.questions.reviews.approve', $question), [
                'review_note' => 'Nội dung ổn.',
            ])
            ->assertRedirect(route('teach.questions.reviews.index', ['tab' => 'approved']));

        $question->refresh();

        $this->assertSame(QuestionStatus::PendingPublish, $question->status);
        $this->assertSame($versionBefore, (int) $question->version);
        $this->assertSame($instructor->id, (int) $question->instructor_id);
        $this->assertNull($question->rejection_reason);
        $this->assertDatabaseHas('question_review_requests', [
            'question_id' => $question->id,
            'status' => QuestionReviewStatus::Approved->value,
            'reviewed_by' => $instructor->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'teach.question.instructor_approved',
        ]);
    }

    public function test_instructor_can_reject_with_reason(): void
    {
        $instructor = $this->instructor();
        $question = $this->makeInReviewQuestion();
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
        $this->assertDatabaseHas('question_review_requests', [
            'question_id' => $question->id,
            'status' => QuestionReviewStatus::Rejected->value,
        ]);
    }

    public function test_reject_requires_reason(): void
    {
        $instructor = $this->instructor();
        $question = $this->makeInReviewQuestion();

        $this->actingAs($instructor)
            ->from(route('teach.questions.reviews.show', $question))
            ->post(route('teach.questions.reviews.reject', $question), [
                'review_note' => '',
            ])
            ->assertRedirect(route('teach.questions.reviews.show', $question))
            ->assertSessionHasErrors('review_note');

        $this->assertSame(QuestionStatus::InReview, $question->fresh()->status);
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
        $question->medicalTaxonomyNodes()->sync([$this->topic->id]);

        return $question;
    }

    private function makeInReviewQuestion(): Question
    {
        $creator = User::factory()->create();
        $creator->assignRole(Role::ContentEditor->value);

        $question = Question::factory()->create([
            'stem' => 'Bệnh nhân sốt cao 3 ngày. Chẩn đoán phù hợp?',
            'explanation' => 'Giải thích lâm sàng đầy đủ cho hàng đợi giảng viên.',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::InReview,
            'created_by' => $creator->id,
            'version' => 0,
        ]);
        $question->medicalTaxonomyNodes()->sync([$this->topic->id]);

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

        return $question->fresh(['options', 'medicalTaxonomyNodes']);
    }
}
