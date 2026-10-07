<?php

declare(strict_types=1);

namespace Modules\Exam\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\LearnerProfile;
use Modules\Auth\Models\Profession;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Exam\Actions\BuildFixedExamPaper;
use Modules\Exam\Database\Seeders\AbcdExamQuestionSeeder;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionSessionSnapshot;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class ExamModuleTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private int $professionId;

    private Lesson $topic;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::findOrCreate(Role::Student->value, 'web');
        $this->seed(BillingDatabaseSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);
        $profession = Profession::query()->create([
            'code' => 'resident-exam-test',
            'name' => 'Bác sĩ nội trú',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        LearnerProfile::query()->create([
            'user_id' => $this->user->id,
            'profession_id' => $profession->id,
            'onboarding_completed_at' => now(),
        ]);
        $this->professionId = (int) $profession->id;
        $this->topic = $this->makeLesson([
            'name' => 'Nội tổng quát',
            'slug' => 'noi-tong-quat-exam-test',
            'sort_order' => 1,
        ]);
    }

    public function test_exam_index_lists_blueprints_as_ky_thi(): void
    {
        $this->seedWeightedBlueprint('Kỳ thi TNLS', 10);

        $this->actingAs($this->user)
            ->get(route('exam.index'))
            ->assertOk()
            ->assertSee('Chọn kỳ thi')
            ->assertSee('Kỳ thi TNLS')
            ->assertSee('Nâng cấp để tạo đề mới');
    }

    public function test_premium_user_can_create_bai_thi_from_blueprint_and_start(): void
    {
        [$blueprint, , $coreTopic, $catalog] = $this->seedWeightedBlueprint('Resident Matrix', 2);
        $q1 = $this->examPoolQuestion('Resident first');
        $q1->update(['difficulty' => Difficulty::Easy]);
        $q1->lessons()->sync([$this->topic->id]);
        $q1->examCatalogs()->sync([$catalog->id]);
        $q1->professions()->sync([$this->professionId]);
        $q2 = $this->examPoolQuestion('Resident second');
        $q2->lessons()->sync([$this->topic->id]);
        $q2->examCatalogs()->sync([$catalog->id]);
        $q2->professions()->sync([$this->professionId]);

        $this->grantPremium($this->user);

        $this->actingAs($this->user)
            ->post(route('exam.from-blueprint', $catalog))
            ->assertRedirect(route('exam.index'));

        $exam = Exam::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame($blueprint->id, $exam->blueprint_id);
        $this->assertSame(ExamStatus::Published, $exam->status);
        $this->assertSame(2, $exam->questions()->count());
        $this->assertSame(1, $exam->examTopics()->count());
        $this->assertSame($coreTopic->id, $exam->examTopics()->firstOrFail()->core_clinical_topic_id);

        $this->assertSame(0, QuestionSession::query()->count());

        $this->actingAs($this->user)
            ->post(route('exam.start', $exam))
            ->assertRedirect();

        $session = QuestionSession::query()->firstOrFail();
        $this->assertSame(SessionMode::Exam, $session->mode);
        $this->assertSame(SessionSource::Exam, $session->source);
        $this->assertSame($exam->id, $session->exam_id);
        $this->assertSame(2, $session->total);
        $this->assertSame($exam->duration_minutes * 60, $session->time_limit_seconds);
        $this->assertSame(2, QuestionSessionSnapshot::query()->where('session_id', $session->getKey())->count());
    }

    public function test_creating_exam_requires_exam_simulation_entitlement(): void
    {
        [$blueprint, , , $catalog] = $this->seedWeightedBlueprint('Locked Matrix', 1);
        $q = $this->examPoolQuestion('Locked Q');
        $q->lessons()->sync([$this->topic->id]);
        $q->examCatalogs()->sync([$catalog->id]);

        $this->actingAs($this->user)
            ->post(route('exam.from-blueprint', $catalog))
            ->assertRedirect(route('subscription.upgrade'));

        $this->assertSame(0, Exam::query()->count());
        $this->assertSame(0, QuestionSession::query()->count());
    }

    public function test_learner_can_retry_own_exam_paper(): void
    {
        $this->grantPremium($this->user);
        $question = $this->examPoolQuestion('Retry stem');
        $question->lessons()->sync([$this->topic->id]);

        $exam = Exam::query()->create([
            'user_id' => $this->user->id,
            'title' => 'Bài thi cũ',
            'duration_minutes' => 60,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);
        $exam->questions()->attach($question->getKey(), ['order' => 1]);

        $this->actingAs($this->user)
            ->post(route('exam.start', $exam))
            ->assertRedirect();

        $session = QuestionSession::query()->firstOrFail();
        $this->assertSame($exam->id, $session->exam_id);
        $this->assertSame(1, $session->total);
    }

    public function test_learner_cannot_start_another_users_exam(): void
    {
        $this->grantPremium($this->user);
        $other = User::factory()->create();
        $other->assignRole(Role::Student->value);

        $exam = Exam::query()->create([
            'user_id' => $other->id,
            'title' => 'Bài của người khác',
            'duration_minutes' => 60,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('exam.start', $exam))
            ->assertForbidden();
    }

    public function test_exam_summary_renders_when_exam_id_is_missing(): void
    {
        $question = $this->examPoolQuestion('Orphan session');

        $session = QuestionSession::factory()->create([
            'user_id' => $this->user->getKey(),
            'mode' => SessionMode::Exam,
            'source' => SessionSource::Exam,
            'status' => SessionStatus::Completed,
            'exam_id' => null,
            'filters' => ['exam_id' => null],
            'question_ids' => [$question->getKey()],
            'total' => 1,
            'answered_count' => 1,
            'correct_count' => 1,
        ]);

        app(QuestionSessionSnapshots::class)->capture($session);

        $this->actingAs($this->user)
            ->get(route('exam.summary', $session))
            ->assertOk()
            ->assertDontSee('Làm lại');
    }

    public function test_exam_summary_retry_uses_exam_id_when_present(): void
    {
        $question = $this->examPoolQuestion('Summary retry');
        $exam = Exam::query()->create([
            'user_id' => $this->user->id,
            'title' => 'Bài retry',
            'duration_minutes' => 60,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);
        $exam->questions()->attach($question->getKey(), ['order' => 1]);

        $session = QuestionSession::factory()->create([
            'user_id' => $this->user->getKey(),
            'mode' => SessionMode::Exam,
            'source' => SessionSource::Exam,
            'status' => SessionStatus::Completed,
            'exam_id' => $exam->getKey(),
            'question_ids' => [$question->getKey()],
            'total' => 1,
            'answered_count' => 1,
            'correct_count' => 1,
        ]);

        app(QuestionSessionSnapshots::class)->capture($session);

        $this->actingAs($this->user)
            ->get(route('exam.summary', $session))
            ->assertOk()
            ->assertSee('Làm lại')
            ->assertSee(route('exam.start', $exam->getKey()), false);
    }

    public function test_exam_review_ignores_legacy_question_stem_image(): void
    {
        $question = Question::factory()
            ->free()
            ->withOptions()
            ->create([
                'stem' => 'Hình ảnh X-quang ngực',
                'stem_image_path' => 'questions/test-chest-xray.jpg',
                'difficulty' => Difficulty::Medium,
                'status' => QuestionStatus::Published,
                'is_priority' => false,
            ]);

        $exam = Exam::query()->create([
            'user_id' => $this->user->id,
            'title' => 'Exam Image Test',
            'description' => 'Test exam',
            'duration_minutes' => 60,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);
        $exam->questions()->attach($question->getKey());

        $session = QuestionSession::factory()->create([
            'user_id' => $this->user->getKey(),
            'mode' => SessionMode::Exam,
            'source' => SessionSource::Exam,
            'status' => SessionStatus::Completed,
            'exam_id' => $exam->getKey(),
            'question_ids' => [$question->getKey()],
            'total' => 1,
            'answered_count' => 1,
            'correct_count' => 1,
        ]);

        app(QuestionSessionSnapshots::class)->capture($session);

        $this->actingAs($this->user)
            ->get(route('exam.review', $session))
            ->assertOk()
            ->assertSee('Xem lại kỳ thi')
            ->assertDontSee('test-chest-xray.jpg')
            ->assertDontSee('imageViewerOpen');
    }

    public function test_sample_paper_is_fixed_and_available_to_free_learners(): void
    {
        [$blueprint, , , $catalog] = $this->seedWeightedBlueprint('Sample', 10);
        foreach ([Difficulty::VeryEasy, Difficulty::Easy, Difficulty::Easy, Difficulty::Easy,
            Difficulty::Medium, Difficulty::Medium, Difficulty::Medium,
            Difficulty::Hard, Difficulty::Hard, Difficulty::VeryHard] as $index => $difficulty) {
            $question = $this->examPoolQuestion('Fixed '.$index);
            $question->update(['difficulty' => $difficulty, 'is_free' => false]);
            $question->lessons()->sync([$this->topic->id]);
            $question->professions()->sync([$this->professionId]);
            $question->examCatalogs()->sync([$catalog->id]);
        }
        $paper = app(BuildFixedExamPaper::class)->handle($catalog);
        $this->actingAsWithWebSession($this->user)->post(route('exam.start', $paper))->assertNotFound();
        $this->assertSame(['easy' => 4, 'medium' => 3, 'hard' => 3], $paper->matrix_snapshot['difficulty_quotas']);
        $this->assertSame(10, count($paper->paper_snapshot));
        $paper->update(['status' => ExamStatus::Published, 'is_published' => true]);
        $catalog->forceFill(['sample_exam_id' => $paper->id])->save();
        $first = $paper->paper_snapshot[0];
        Question::findOrFail($first['question_id'])->update(['stem' => 'Changed source']);
        $this->actingAsWithWebSession($this->user)->post(route('exam.start', $paper))->assertRedirect();
        $one = QuestionSession::query()->latest()->firstOrFail();
        $this->actingAsWithWebSession($this->user)->get(route('exam.session', $one))->assertOk()->assertSee($first['payload']['stem']);
        $this->assertSame(array_column($paper->paper_snapshot, 'question_id'), $one->question_ids);
        $this->assertSame($first['payload'], $one->snapshots()->where('question_id', $first['question_id'])->firstOrFail()->payload);
        $other = User::factory()->create();
        $other->assignRole(Role::Student->value);
        LearnerProfile::create(['user_id' => $other->id, 'profession_id' => $this->professionId, 'onboarding_completed_at' => now()]);
        $this->actingAsWithWebSession($other)->post(route('exam.start', $paper))->assertRedirect();
        $two = QuestionSession::where('user_id', $other->id)->firstOrFail();
        $this->assertSame($one->question_ids, $two->question_ids);
        $this->assertNotSame($one->id, $two->id);
        $this->assertSame(1, Exam::where('kind', 'sample')->count());
        $this->actingAsWithWebSession($other)->post(route('admin.exams.publish-sample', $paper))->assertForbidden();
        $this->actingAsWithWebSession($other)->get(route('exam.session', $one))->assertForbidden();
    }

    public function test_premium_papers_use_new_questions_then_reuse_when_the_catalog_pool_is_exhausted(): void
    {
        [, , , $catalog] = $this->seedWeightedBlueprint('Three papers', 10);
        foreach (['easy' => 12, 'medium' => 9, 'hard' => 9] as $difficulty => $count) {
            for ($index = 0; $index < $count; $index++) {
                $question = $this->examPoolQuestion($difficulty.' '.$index);
                $question->update(['difficulty' => Difficulty::from($difficulty)]);
                $question->lessons()->sync([$this->topic->id]);
                $question->professions()->sync([$this->professionId]);
                $question->examCatalogs()->sync([$catalog->id]);
            }
        }

        $sample = app(BuildFixedExamPaper::class)->handle($catalog);
        $sample->update(['status' => ExamStatus::Published, 'is_published' => true]);
        $catalog->forceFill(['sample_exam_id' => $sample->id])->save();
        $this->grantPremium($this->user);

        for ($index = 0; $index < 2; $index++) {
            $this->actingAsWithWebSession($this->user)
                ->post(route('exam.from-blueprint', $catalog))
                ->assertRedirect();
        }

        $papers = Exam::query()->where('kind', 'personal')->where('user_id', $this->user->id)->get();
        $this->assertCount(2, $papers);
        $questionIds = collect([$sample, ...$papers->all()])
            ->flatMap(fn (Exam $exam) => array_column($exam->paper_snapshot, 'question_id'));
        $this->assertCount(30, $questionIds->unique());
        $this->assertSame(0, QuestionSession::query()->where('user_id', $this->user->id)->count());

        $this->actingAsWithWebSession($this->user)
            ->from(route('exam.index'))
            ->post(route('exam.from-blueprint', $catalog))
            ->assertRedirect(route('exam.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '10/10 câu'));
        $third = Exam::query()->where('kind', 'personal')->latest('id')->firstOrFail();
        $this->assertSame(10, $third->matrix_snapshot['reused_question_count']);
        $this->assertSame(3, Exam::query()->where('kind', 'personal')->count());
        $this->assertSame(0, QuestionSession::query()->where('user_id', $this->user->id)->count());
    }

    public function test_fixed_paper_shortage_rolls_back(): void
    {
        [, , , $catalog] = $this->seedWeightedBlueprint('Shortage', 10);
        try {
            app(BuildFixedExamPaper::class)->handle($catalog);
            $this->fail('Expected shortage');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('40/30/30', $exception->getMessage());
        }
        $this->assertSame(0, Exam::count());
    }

    public function test_abcd_catalog_seeds_600_matching_questions_without_duplicates(): void
    {
        [, , , $catalog] = $this->seedWeightedBlueprint('ABCD matrix', 40);
        $catalog->update(['code' => 'abcd']);

        $this->seed(AbcdExamQuestionSeeder::class);
        $this->seed(AbcdExamQuestionSeeder::class);

        $this->assertSame(600, $catalog->questions()->count());
        $this->assertSame(600, $catalog->questions()->distinct('questions.id')->count('questions.id'));
        $paper = app(BuildFixedExamPaper::class)->handle($catalog);
        $this->assertSame(40, $paper->questionCount());
        $this->assertSame(['easy' => 16, 'medium' => 12, 'hard' => 12], $paper->matrix_snapshot['difficulty_quotas']);
    }

    private function examPoolQuestion(string $stem): Question
    {
        return Question::factory()
            ->free()
            ->withOptions()
            ->create([
                'stem' => $stem,
                'difficulty' => Difficulty::Medium,
                'status' => QuestionStatus::Published,
                'is_priority' => false,
            ]);
    }

    /**
     * @return array{0: Blueprint, 1: BlueprintSection, 2: CoreClinicalTopic, 3: ExamCatalog}
     */
    private function seedWeightedBlueprint(string $name, int $total): array
    {
        $blueprint = Blueprint::query()->create([
            'name' => $name,
            'slug' => 'bp-'.uniqid(),
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'total_questions' => $total,
        ]);

        $section = BlueprintSection::query()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'Nội',
            'slug' => 'noi-'.uniqid(),
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'weight_min' => 100,
            'weight_max' => 100,
        ]);

        $coreTopic = CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $section->id,
            'name' => 'Tim mạch',
            'slug' => 'tim-'.uniqid(),
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'weight' => 100,
        ]);

        $coreTopic->lessons()->sync([$this->topic->id]);

        $catalog = ExamCatalog::query()->create([
            'name' => $name,
            'slug' => 'ky-thi-'.uniqid(),
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'blueprint_id' => $blueprint->id,
        ]);
        $catalog->professions()->sync([$this->professionId]);

        return [$blueprint, $section, $coreTopic, $catalog];
    }

    private function grantPremium(User $user): void
    {
        $premium = Plan::query()->where('slug', 'premium')->firstOrFail();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'plan_id' => $premium->getKey(),
            'status' => 'active',
            'source' => 'test',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }
}
