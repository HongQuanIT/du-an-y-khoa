<?php

declare(strict_types=1);

namespace Modules\Exam\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\Auth\Models\LearnerProfile;
use Modules\Auth\Models\Profession;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionSessionSnapshot;
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
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

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
            ->assertSee('Nâng cấp để tạo bài thi');
    }

    public function test_premium_user_can_create_bai_thi_from_blueprint_and_start(): void
    {
        [$blueprint, , $coreTopic, $catalog] = $this->seedWeightedBlueprint('Resident Matrix', 2);
        $q1 = $this->examPoolQuestion('Resident first');
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
            ->assertRedirect();

        $exam = Exam::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame($blueprint->id, $exam->blueprint_id);
        $this->assertSame(ExamStatus::Published, $exam->status);
        $this->assertSame(2, $exam->questions()->count());
        $this->assertSame(1, $exam->examTopics()->count());
        $this->assertSame($coreTopic->id, $exam->examTopics()->firstOrFail()->core_clinical_topic_id);

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
            'status' => \Modules\QuestionBank\Enums\SessionStatus::Completed,
            'exam_id' => null,
            'filters' => ['exam_id' => null],
            'question_ids' => [$question->getKey()],
            'total' => 1,
            'answered_count' => 1,
            'correct_count' => 1,
        ]);

        app(\Modules\QuestionBank\Services\QuestionSessionSnapshots::class)->capture($session);

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
            'status' => \Modules\QuestionBank\Enums\SessionStatus::Completed,
            'exam_id' => $exam->getKey(),
            'question_ids' => [$question->getKey()],
            'total' => 1,
            'answered_count' => 1,
            'correct_count' => 1,
        ]);

        app(\Modules\QuestionBank\Services\QuestionSessionSnapshots::class)->capture($session);

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
            'status' => \Modules\QuestionBank\Enums\SessionStatus::Completed,
            'exam_id' => $exam->getKey(),
            'question_ids' => [$question->getKey()],
            'total' => 1,
            'answered_count' => 1,
            'correct_count' => 1,
        ]);

        app(\Modules\QuestionBank\Services\QuestionSessionSnapshots::class)->capture($session);

        $this->actingAs($this->user)
            ->get(route('exam.review', $session))
            ->assertOk()
            ->assertSee('Xem lại kỳ thi')
            ->assertDontSee('test-chest-xray.jpg')
            ->assertDontSee('imageViewerOpen');
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
