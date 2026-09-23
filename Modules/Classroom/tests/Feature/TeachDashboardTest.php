<?php

declare(strict_types=1);

namespace Modules\Classroom\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;
use Modules\Classroom\Models\LiveSession;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewRequest;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class TeachDashboardTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private Lesson $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->topic = $this->makeLesson([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-teach-dashboard',
            'sort_order' => 1,
        ]);
    }

    public function test_instructor_dashboard_shows_real_kpis(): void
    {
        $instructor = $this->instructor();
        $pendingClass = $this->seedClassroom($instructor, ClassroomPurpose::FeedbackReview, ClassroomStatus::PendingApproval);
        $activeClass = $this->seedClassroom($instructor, ClassroomPurpose::ExamReview, ClassroomStatus::Active);

        LiveSession::query()->create([
            'classroom_id' => $activeClass->id,
            'title' => 'Buổi live sắp tới',
            'status' => LiveSessionStatus::Scheduled,
            'scheduled_at' => now()->addHours(3),
        ]);

        $pendingQuestion = $this->makeInReviewQuestion($instructor);

        Cache::flush();

        $this->actingAs($instructor)
            ->get(route('teach.dashboard'))
            ->assertOk()
            ->assertSee('Bảng điều khiển giảng viên')
            ->assertSee('Lớp của tôi')
            ->assertSee('Đang live')
            ->assertSee('Sắp live')
            ->assertSee('Câu chờ duyệt')
            ->assertSee('Cần chú ý')
            ->assertSee('1 lớp đang chờ Admin duyệt')
            ->assertSee('Thao tác nhanh')
            ->assertSee('Tạo lớp')
            ->assertSee('Hoạt động lớp học')
            ->assertSee('Duyệt câu hỏi')
            ->assertSee('Buổi live sắp tới')
            ->assertSee(strip_tags((string) $pendingQuestion->stem));

        $html = $this->actingAs($instructor)->get(route('teach.dashboard'))->getContent();
        $this->assertMatchesRegularExpression('/Lớp của tôi[\s\S]*?>2</', $html);
        $this->assertMatchesRegularExpression('/Sắp live[\s\S]*?>1</', $html);
        $this->assertMatchesRegularExpression('/Câu chờ duyệt[\s\S]*?>1</', $html);
    }

    public function test_student_cannot_access_teach_dashboard(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('teach.dashboard'))
            ->assertRedirect();
    }

    /** @param  array<string, mixed>  $attributes */
    private function instructor(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::Instructor->value);

        return $user;
    }

    private function seedClassroom(
        User $host,
        ClassroomPurpose $purpose,
        ClassroomStatus $status = ClassroomStatus::Active,
    ): Classroom {
        $classroom = Classroom::query()->create([
            'title' => 'Lớp dashboard '.$purpose->value.' '.$status->value,
            'host_user_id' => $host->id,
            'purpose' => $purpose,
            'visibility' => ClassroomVisibility::Unlisted,
            'join_code' => 'TD'.strtoupper(substr(uniqid(), -6)),
            'status' => $status,
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

    private function makeInReviewQuestion(User $assigned): Question
    {
        $creator = User::factory()->create();
        $creator->assignRole(Role::ContentEditor->value);

        $question = Question::factory()->create([
            'stem' => 'Dashboard: câu chờ duyệt chuyên môn cho giảng viên?',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::InReview,
            'created_by' => $creator->id,
            'assigned_instructor_id' => $assigned->id,
            'version' => 0,
        ]);
        $question->lessons()->sync([$this->topic->id]);

        QuestionReviewRequest::query()->create([
            'question_id' => $question->id,
            'action' => QuestionReviewAction::Create,
            'status' => QuestionReviewStatus::Pending,
            'requested_by' => $creator->id,
        ]);

        return $question->fresh(['lessons']) ?? $question;
    }
}
