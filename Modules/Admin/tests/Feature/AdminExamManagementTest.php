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
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Topic;
use Tests\TestCase;

final class AdminExamManagementTest extends TestCase
{
    use RefreshDatabase;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->topic = Topic::query()->create([
            'name' => 'Nội tim mạch',
            'slug' => 'noi-tim-mach-exam-test',
            'type' => 'specialty',
            'order' => 1,
        ]);
    }

    public function test_admin_can_create_exam_with_questions_and_publish_in_one_submit(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->question('Câu hỏi tạo đề trong một trang?', true);

        $response = $this->actingAsStaff($admin)
            ->post(route('admin.exams.store'), [
                'title' => 'Kỳ thi nội trú',
                'description' => 'Bài thi mô phỏng cho học viên.',
                'duration_minutes' => 120,
                'status' => ExamStatus::Published->value,
                'questions' => [$question->getKey()],
            ]);

        $exam = Exam::query()->firstOrFail();

        $response->assertRedirect(route('admin.exams.edit', $exam));
        $this->assertTrue($exam->is_published);
        $this->assertSame(ExamStatus::Published, $exam->status);
        $this->assertSame(120, $exam->duration_minutes);
        $this->assertSame('Kỳ thi nội trú', $exam->title);
        $this->assertSame(1, $exam->questions()->count());
    }

    public function test_admin_create_page_includes_question_builder(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $this->question('Câu hỏi có sẵn để thêm?', true);

        $this->actingAsStaff($admin)
            ->get(route('admin.exams.create'))
            ->assertOk()
            ->assertSee('Tạo kỳ thi mới')
            ->assertSee('Đề thi')
            ->assertSee('Thư viện câu hỏi');
    }

    public function test_admin_can_see_exam_question_count_on_index_and_edit_pages(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $exam = Exam::query()->create([
            'title' => 'Kỳ thi 1',
            'description' => 'Mô tả',
            'duration_minutes' => 90,
            'status' => ExamStatus::Draft,
            'is_published' => false,
        ]);
        $question = $this->question('Câu hỏi chẩn đoán nào phù hợp?', true);
        $exam->questions()->sync([
            $question->getKey() => ['order' => 1],
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.exams.index'))
            ->assertOk()
            ->assertSee('1 câu');

        $this->actingAsStaff($admin)
            ->get(route('admin.exams.edit', $exam))
            ->assertOk()
            ->assertSee('Đề thi')
            ->assertSee('Đã chọn');
    }

    public function test_admin_cannot_publish_exam_without_questions(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $exam = Exam::query()->create([
            'title' => 'Kỳ thi 2',
            'description' => 'Mô tả',
            'duration_minutes' => 90,
            'status' => ExamStatus::Draft,
            'is_published' => false,
        ]);

        $this->actingAsStaff($admin)
            ->put(route('admin.exams.update', $exam), [
                'title' => 'Kỳ thi 2',
                'description' => 'Mô tả',
                'duration_minutes' => 90,
                'status' => ExamStatus::Published->value,
                'questions' => [],
            ])
            ->assertSessionHasErrors(['status']);

        $this->assertFalse($exam->fresh()->is_published);
    }

    public function test_admin_cannot_create_published_exam_without_questions(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->post(route('admin.exams.store'), [
                'title' => 'Kỳ thi trống',
                'description' => 'Mô tả',
                'duration_minutes' => 90,
                'status' => ExamStatus::Published->value,
                'questions' => [],
            ])
            ->assertSessionHasErrors(['status']);

        $this->assertSame(0, Exam::query()->count());
    }

    public function test_admin_can_save_exam_as_draft_in_one_page_flow(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->question('Câu hỏi để lưu nháp?', true);

        $this->actingAsStaff($admin)
            ->post(route('admin.exams.store'), [
                'title' => 'Kỳ thi nháp',
                'description' => 'Mô tả',
                'duration_minutes' => 75,
                'status' => ExamStatus::Draft->value,
                'questions' => [$question->getKey()],
            ])
            ->assertRedirect();

        $exam = Exam::query()->firstOrFail();

        $this->assertSame(ExamStatus::Draft, $exam->status);
        $this->assertFalse($exam->is_published);
        $this->assertSame(1, $exam->questions()->count());
    }

    private function question(string $stem, bool $published = true): Question
    {
        return Question::factory()
            ->free()
            ->withOptions()
            ->create([
                'stem' => $stem,
                'difficulty' => Difficulty::Medium,
                'status' => $published ? QuestionStatus::Published : QuestionStatus::Draft,
                'topic_id' => $this->topic->getKey(),
            ]);
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->enrollTwoFactor($user);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }

    private function enrollTwoFactor(User $user): void
    {
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);
    }
}
