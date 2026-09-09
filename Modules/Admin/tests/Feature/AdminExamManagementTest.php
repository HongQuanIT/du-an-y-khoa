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
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class AdminExamManagementTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private Lesson $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->topic = $this->makeMedicalNode([
            'name' => 'Nội tim mạch',
            'slug' => 'noi-tim-mach-exam-test',
            'sort_order' => 1,
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

    public function test_admin_create_page_includes_matrix_allocation_and_manual_fallback(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $this->question('Câu hỏi có sẵn để thêm?', true);

        $this->actingAsStaff($admin)
            ->get(route('admin.exams.create'))
            ->assertOk()
            ->assertSee('Tạo kỳ thi mới')
            ->assertSee('Phân bổ theo ma trận đề thi')
            ->assertSee('Đề thi (chọn thủ công — tùy chọn)')
            ->assertSee('Thư viện câu hỏi');
    }

    public function test_admin_can_create_exam_from_blueprint_difficulty_allocation(): void
    {
        $admin = $this->staffUser(Role::Admin);
        [$blueprint, $section, $coreTopic] = $this->seedBlueprintTopicMappedTo($this->topic);

        $easy = $this->examPoolQuestion('Easy pool 1', Difficulty::Easy);
        $easy->lessons()->sync([$this->topic->id]);
        $mediumA = $this->examPoolQuestion('Medium pool 1', Difficulty::Medium);
        $mediumA->lessons()->sync([$this->topic->id]);
        $mediumB = $this->examPoolQuestion('Medium pool 2', Difficulty::Medium);
        $mediumB->lessons()->sync([$this->topic->id]);

        $response = $this->actingAsStaff($admin)
            ->post(route('admin.exams.store'), [
                'title' => 'Kỳ thi theo ma trận',
                'description' => 'Generate theo độ khó',
                'duration_minutes' => 90,
                'status' => ExamStatus::Draft->value,
                'blueprint_id' => $blueprint->id,
                'section_ids' => [$section->id],
                'exam_topics' => [
                    [
                        'core_clinical_topic_id' => $coreTopic->id,
                        'sort_order' => 0,
                        'difficulty_counts' => [
                            'very_easy' => 0,
                            'easy' => 1,
                            'medium' => 2,
                            'hard' => 0,
                            'very_hard' => 0,
                        ],
                    ],
                ],
            ]);

        $exam = Exam::query()->firstOrFail();
        $response->assertRedirect(route('admin.exams.edit', $exam));

        $this->assertSame($blueprint->id, $exam->blueprint_id);
        $this->assertSame(1, $exam->examTopics()->count());

        $examTopic = $exam->examTopics()->firstOrFail();
        $this->assertSame(3, $examTopic->question_count);
        $this->assertSame(1, $examTopic->difficulty_counts['easy']);
        $this->assertSame(2, $examTopic->difficulty_counts['medium']);
        $this->assertSame(3, $exam->questions()->count());
        $this->assertTrue($exam->questions()->whereKey($easy->id)->exists());
        $this->assertTrue($exam->questions()->whereKey($mediumA->id)->exists());
        $this->assertTrue($exam->questions()->whereKey($mediumB->id)->exists());
    }

    public function test_admin_cannot_generate_when_difficulty_pool_is_short(): void
    {
        $admin = $this->staffUser(Role::Admin);
        [$blueprint, $section, $coreTopic] = $this->seedBlueprintTopicMappedTo($this->topic);

        $easy = $this->examPoolQuestion('Only one easy', Difficulty::Easy);
        $easy->lessons()->sync([$this->topic->id]);

        $this->actingAsStaff($admin)
            ->post(route('admin.exams.store'), [
                'title' => 'Kỳ thi thiếu pool',
                'description' => 'Thiếu câu',
                'duration_minutes' => 90,
                'status' => ExamStatus::Draft->value,
                'blueprint_id' => $blueprint->id,
                'section_ids' => [$section->id],
                'exam_topics' => [
                    [
                        'core_clinical_topic_id' => $coreTopic->id,
                        'difficulty_counts' => [
                            'very_easy' => 0,
                            'easy' => 2,
                            'medium' => 0,
                            'hard' => 0,
                            'very_hard' => 0,
                        ],
                    ],
                ],
            ])
            ->assertSessionHasErrors(['exam_topics']);

        $this->assertSame(0, Exam::query()->count());
    }

    public function test_topic_eligibility_returns_counts_by_difficulty(): void
    {
        $admin = $this->staffUser(Role::Admin);
        [, , $coreTopic] = $this->seedBlueprintTopicMappedTo($this->topic);

        $easy = $this->examPoolQuestion('Elig easy', Difficulty::Easy);
        $easy->lessons()->sync([$this->topic->id]);
        $hard = $this->examPoolQuestion('Elig hard', Difficulty::Hard);
        $hard->lessons()->sync([$this->topic->id]);

        $this->actingAsStaff($admin)
            ->get(route('admin.exams.topic-eligibility', [
                'core_clinical_topic_ids' => [$coreTopic->id],
            ]))
            ->assertOk()
            ->assertJsonPath('data.'.$coreTopic->id.'.total', 2)
            ->assertJsonPath('data.'.$coreTopic->id.'.by_difficulty.easy', 1)
            ->assertJsonPath('data.'.$coreTopic->id.'.by_difficulty.hard', 1)
            ->assertJsonPath('data.'.$coreTopic->id.'.by_difficulty.medium', 0);
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
            ]);
    }

    private function examPoolQuestion(string $stem, Difficulty $difficulty): Question
    {
        return Question::factory()
            ->free()
            ->withOptions()
            ->create([
                'stem' => $stem,
                'difficulty' => $difficulty,
                'status' => QuestionStatus::Private,
                'exam_flag' => true,
            ]);
    }

    /**
     * @return array{0: Blueprint, 1: BlueprintSection, 2: CoreClinicalTopic}
     */
    private function seedBlueprintTopicMappedTo(Lesson $node): array
    {
        $blueprint = Blueprint::query()->create([
            'name' => 'Ma trận thi thử',
            'slug' => 'ma-tran-thi-thu-'.uniqid(),
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);

        $section = BlueprintSection::query()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'Hệ tim mạch',
            'slug' => 'he-tim-mach-'.uniqid(),
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);

        $coreTopic = CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $section->id,
            'name' => 'Đau ngực',
            'slug' => 'dau-nguc-'.uniqid(),
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);

        $coreTopic->lessons()->sync([$node->id]);

        return [$blueprint, $section, $coreTopic];
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
