<?php

declare(strict_types=1);

namespace Modules\Exam\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionScopeType;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionScope;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionSessionSnapshot;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;
use Tests\Support\CreatesMedicalTaxonomy;


final class ExamModuleTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private \Modules\QuestionBank\Models\MedicalTaxonomyNode $topic;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::findOrCreate(Role::Student->value, 'web');
        $this->seed(BillingDatabaseSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);
        $this->topic = $this->makeMedicalNode([
            'name' => 'Nội tổng quát',
            'slug' => 'noi-tong-quat-exam-test',
            'node_type' => 'system',
            'sort_order' => 1,
        ]);
    }

    public function test_exam_index_renders_catalog_and_accessible_counts(): void
    {
        $this->examQuestion('Resident first', 'resident');
        $this->examQuestion('Resident second', 'resident');
        $this->examQuestion('NBME first', 'nbme');

        $this->actingAs($this->user)
            ->get(route('exam.index'))
            ->assertOk()
            ->assertSee('Exam simulation')
            ->assertSee('resident')
            ->assertSee('nbme')
            ->assertSee('Nâng cấp để bắt đầu');
    }

    public function test_premium_user_can_start_exam_session_from_catalog(): void
    {
        $first = $this->examQuestion('Resident first', 'Resident');
        $second = $this->examQuestion('Resident second', 'Resident');
        $exam = \Modules\Exam\Models\Exam::where('title', 'Resident')->first();
        $this->grantPremium($this->user);

        $this->actingAs($this->user)
            ->post(route('exam.start', $exam->id), ['count' => 2])
            ->assertRedirect();

        $session = QuestionSession::query()->firstOrFail();

        $this->assertSame(SessionMode::Exam, $session->mode);
        $this->assertSame(SessionSource::Exam, $session->source);
        $this->assertSame($exam->id, $session->exam_id);
        $this->assertSame(2, $session->total);
        $this->assertSame(180 * 60, $session->time_limit_seconds);
        $this->assertEqualsCanonicalizing([$first->getKey(), $second->getKey()], $session->question_ids);
        $this->assertSame(2, QuestionSessionSnapshot::query()->where('session_id', $session->getKey())->count());
    }

    public function test_starting_exam_requires_exam_simulation_entitlement(): void
    {
        $this->examQuestion('Resident first', 'Resident');
        $exam = \Modules\Exam\Models\Exam::where('title', 'Resident')->first();

        $this->actingAs($this->user)
            ->post(route('exam.start', $exam->id), ['count' => 1])
            ->assertRedirect(route('subscription.upgrade'));

        $this->assertSame(0, QuestionSession::query()->count());
    }

    public function test_exam_summary_renders_when_exam_id_is_missing(): void
    {
        $question = $this->examQuestion('Resident first', 'Resident');

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
        $question = $this->examQuestion('Resident first', 'Resident');
        $exam = \Modules\Exam\Models\Exam::query()->where('title', 'Resident')->firstOrFail();

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

    public function test_exam_review_displays_question_stem_image(): void
    {
        $question = Question::factory()
            ->free()
            ->withOptions()
            ->create([
                'stem' => 'Hình ảnh X-quang ngực',
                'stem_image_path' => 'questions/test-chest-xray.jpg',
                'difficulty' => Difficulty::Medium,
            ]);

        $exam = \Modules\Exam\Models\Exam::query()->firstOrCreate(
            ['title' => 'Exam Image Test'],
            [
                'description' => 'Test exam',
                'duration_minutes' => 60,
                'status' => \Modules\Exam\Enums\ExamStatus::Published,
                'is_published' => true,
            ]
        );
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
            ->assertSee('test-chest-xray.jpg')
            ->assertSee('imageViewerOpen');
    }

    private function examQuestion(string $stem, string $examKey): Question
    {
        $question = Question::factory()
            ->free()
            ->withOptions()
            ->create([
                'stem' => $stem,
                'difficulty' => Difficulty::Medium,
                            ]);

        $exam = \Modules\Exam\Models\Exam::query()->firstOrCreate(
            ['title' => $examKey],
            [
                'description' => 'Test exam',
                'duration_minutes' => 180,
                'status' => \Modules\Exam\Enums\ExamStatus::Published,
                'is_published' => true,
            ]
        );
        $exam->questions()->attach($question->getKey());

        return $question;
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
