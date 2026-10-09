<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\Profession;
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
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\Question;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class AdminExamManagementTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_index_lists_learner_created_exams_read_only(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $learner = User::factory()->create(['name' => 'Nguyen Van A', 'email' => 'learner@example.com']);
        $blueprint = Blueprint::query()->create([
            'name' => 'TNLS 2026',
            'slug' => 'tnls-2026',
            'code' => 'TNLS',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'total_questions' => 100,
        ]);

        Exam::query()->create([
            'user_id' => $learner->id,
            'blueprint_id' => $blueprint->id,
            'title' => 'TNLS 2026',
            'description' => 'Bài thi học viên',
            'duration_minutes' => 150,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.exams.index'))
            ->assertOk()
            ->assertSee('Bài thi')
            ->assertSee('Nguyen Van A')
            ->assertSee('TNLS 2026')
            ->assertSee('learner@example.com')
            ->assertDontSee('Tạo kỳ thi');
    }

    public function test_admin_can_view_exam_detail(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $learner = User::factory()->create(['name' => 'Tran B']);
        $exam = Exam::query()->create([
            'user_id' => $learner->id,
            'title' => 'Bài thi chi tiết',
            'duration_minutes' => 90,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.exams.show', $exam))
            ->assertOk()
            ->assertSee('Bài thi chi tiết')
            ->assertSee('Tran B')
            ->assertSee('Chỉ xem');
    }

    public function test_admin_create_routes_are_removed(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->get('/admin/exams/create')
            ->assertNotFound();
    }

    public function test_admin_can_delete_exam(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $exam = Exam::query()->create([
            'title' => 'Xóa tôi',
            'duration_minutes' => 60,
            'status' => ExamStatus::Draft,
            'is_published' => false,
        ]);

        $this->actingAsStaff($admin)
            ->delete(route('admin.exams.destroy', $exam))
            ->assertRedirect(route('admin.exams.index'));

        $this->assertSame(0, Exam::query()->count());
    }

    public function test_admin_can_publish_sample_and_replace_pointer_without_deleting_old_paper(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $blueprint = Blueprint::create(['name' => 'Sample matrix', 'slug' => 'sample-matrix', 'status' => TaxonomyStatus::Active, 'total_questions' => 1]);
        $catalog = ExamCatalog::create(['name' => 'Sample catalog', 'slug' => 'sample-catalog', 'status' => TaxonomyStatus::Active, 'blueprint_id' => $blueprint->id]);
        $profession = Profession::create(['code' => 'sample-test', 'name' => 'Bác sĩ', 'is_active' => true]);
        $catalog->professions()->attach($profession);
        $section = BlueprintSection::create(['blueprint_id' => $blueprint->id, 'name' => 'Nội', 'slug' => 'noi', 'status' => TaxonomyStatus::Active, 'weight_min' => 100, 'weight_max' => 100]);
        $topic = CoreClinicalTopic::create(['blueprint_section_id' => $section->id, 'name' => 'Tim', 'slug' => 'tim', 'status' => TaxonomyStatus::Active, 'weight' => 100]);
        $lesson = $this->makeLesson();
        $topic->lessons()->attach($lesson);
        $question = Question::factory()->withOptions()->create(['difficulty' => Difficulty::Easy, 'status' => QuestionStatus::Published]);
        $question->lessons()->attach($lesson);
        $question->professions()->attach($profession);
        $question->examCatalogs()->attach($catalog);
        $this->actingAsStaff($admin)->post(route('admin.exam-catalogs.sample', $catalog))->assertRedirect();
        $paper = Exam::where('kind', 'sample')->firstOrFail();
        $this->actingAsStaff($admin)->get(route('admin.exams.show', $paper))->assertOk()->assertSee('Xuất bản bài mẫu');
        $this->actingAsStaff($admin)->post(route('admin.exams.publish-sample', $paper))->assertRedirect();
        $this->assertSame($paper->id, (int) $catalog->fresh()->sample_exam_id);
        $this->actingAsStaff($admin)->post(route('admin.exam-catalogs.sample', $catalog))
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'Phân bổ độ khó chưa đổi'));
        $next = Exam::latest('id')->firstOrFail();
        $this->actingAsStaff($admin)->post(route('admin.exams.publish-sample', $next))->assertRedirect();
        $this->assertSame($next->id, (int) $catalog->fresh()->sample_exam_id);
        $this->assertDatabaseHas('exams', ['id' => $paper->id, 'status' => 'published']);
        $blueprint->update(['total_questions' => 2]);
        $this->actingAsStaff($admin)->post(route('admin.exams.publish-sample', $paper))->assertStatus(422);
        $this->assertSame($next->id, (int) $catalog->fresh()->sample_exam_id);
    }

    public function test_new_sample_spreads_available_difficulties_across_topics(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $blueprint = Blueprint::create([
            'name' => 'Balanced sample',
            'slug' => 'balanced-sample',
            'status' => TaxonomyStatus::Active,
            'total_questions' => 15,
            'difficulty_easy_percent' => 40,
            'difficulty_medium_percent' => 30,
            'difficulty_hard_percent' => 30,
        ]);
        $catalog = ExamCatalog::create(['name' => 'Balanced catalog', 'slug' => 'balanced-catalog', 'status' => TaxonomyStatus::Active, 'blueprint_id' => $blueprint->id]);
        $profession = Profession::create(['code' => 'balanced-sample', 'name' => 'Bác sĩ', 'is_active' => true]);
        $catalog->professions()->attach($profession);
        $section = BlueprintSection::create(['blueprint_id' => $blueprint->id, 'name' => 'Nội', 'slug' => 'balanced-noi', 'status' => TaxonomyStatus::Active, 'weight_min' => 100, 'weight_max' => 100]);

        foreach ([33.33, 33.33, 33.34] as $index => $weight) {
            $topic = CoreClinicalTopic::create([
                'blueprint_section_id' => $section->id,
                'name' => 'Chủ đề '.($index + 1),
                'slug' => 'balanced-topic-'.($index + 1),
                'status' => TaxonomyStatus::Active,
                'weight' => $weight,
            ]);
            $lesson = $this->makeLesson(['name' => 'Bài học '.($index + 1), 'slug' => 'balanced-lesson-'.($index + 1)]);
            $topic->lessons()->attach($lesson);
            foreach ([Difficulty::Easy, Difficulty::Medium, Difficulty::Hard] as $difficulty) {
                for ($copy = 0; $copy < 5; $copy++) {
                    $question = Question::factory()->withOptions()->create(['difficulty' => $difficulty, 'status' => QuestionStatus::Published]);
                    $question->lessons()->attach($lesson);
                    $question->professions()->attach($profession);
                    $question->examCatalogs()->attach($catalog);
                }
            }
        }

        $distributions = [];
        for ($version = 0; $version < 2; $version++) {
            $this->actingAsStaff($admin)->post(route('admin.exam-catalogs.sample', $catalog))
                ->assertRedirect()
                ->assertSessionHas('status', fn (string $message): bool => $version === 0 || ! str_contains($message, 'Phân bổ độ khó chưa đổi'));
            $paper = Exam::where('kind', 'sample')->latest('id')->firstOrFail();
            $this->assertSame(['easy' => 6, 'medium' => 5, 'hard' => 4], $paper->matrix_snapshot['difficulty_quotas']);
            $this->assertSame(3, $paper->examTopics()->count());
            foreach ($paper->examTopics as $topic) {
                $this->assertSame(5, $topic->question_count);
                $this->assertGreaterThanOrEqual(1, $topic->difficulty_counts['easy'] ?? 0);
                $this->assertGreaterThanOrEqual(1, $topic->difficulty_counts['medium'] ?? 0);
                $this->assertGreaterThanOrEqual(1, $topic->difficulty_counts['hard'] ?? 0);
                $distributions[$version][$topic->core_clinical_topic_id] = $topic->difficulty_counts;
            }
        }
        $this->assertNotSame($distributions[0], $distributions[1]);
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
