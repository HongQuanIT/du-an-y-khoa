<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\StudyPlan\Actions\CompletePlanTaskAction;
use Modules\StudyPlan\Enums\TaskType;
use Modules\StudyPlan\Events\StudyPlanActivity;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;
use Tests\TestCase;
use Tests\Support\CreatesMedicalTaxonomy;


/**
 * Phase 5: REST endpoints reuse the same actions, and the funnel emits its
 * tracking events.
 */
final class StudyPlanApiTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private \Modules\QuestionBank\Models\MedicalTaxonomyNode $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->topic = $this->makeMedicalNode([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach',
            'node_type' => 'system',
            'sort_order' => 0,
        ]);

        Question::factory()->count(10)->create([
            'status' => QuestionStatus::Published,
            'is_free' => true,
        ])->each(fn (Question $question) => $question->medicalTaxonomyNodes()->sync([$this->topic->id]));
    }

    public function test_a_plan_can_be_created_and_read_back(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.study-plan.plans.store'), $this->payload())
            ->assertCreated();

        $planId = $response->json('data.id');

        $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.study-plan.plans.show', $planId))
            ->assertOk()
            ->assertJsonPath('data.attributes.daily_goal_questions', 10)
            ->assertJsonPath('data.attributes.strategy', 'fixed');
    }

    public function test_today_endpoint_returns_the_generated_task(): void
    {
        $plan = $this->createPlan();

        $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.study-plan.tasks.index', $plan))
            ->assertOk()
            ->assertJsonPath('data.0.attributes.task_type', 'questions')
            ->assertJsonPath('data.0.attributes.target', 10);
    }

    public function test_skipping_a_task_over_the_api(): void
    {
        $plan = $this->createPlan();
        $task = $plan->tasks()->whereDate('date', Carbon::today())->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.study-plan.tasks.skip', [$plan, $task]))
            ->assertOk()
            ->assertJsonPath('data.attributes.status', 'skipped');
    }

    public function test_another_learner_cannot_read_the_plan(): void
    {
        $plan = $this->createPlan();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson(route('api.study-plan.plans.show', $plan))
            ->assertForbidden();
    }

    public function test_a_learner_cannot_delete_their_plan_over_the_api(): void
    {
        $plan = $this->createPlan();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('api.study-plan.plans.destroy', $plan))
            ->assertForbidden();

        $this->assertDatabaseHas('study_plans', ['id' => $plan->getKey()]);
    }

    public function test_a_review_task_draws_from_previously_wrong_answers(): void
    {
        $this->seedQuestions(6);

        $plan = StudyPlan::factory()->for($this->user)->create([
            'topic_scope' => [$this->topic->id],
            'daily_goal_questions' => 10,
        ]);

        $reviewTask = StudyPlanTask::factory()->for($plan, 'plan')->create([
            'type' => TaskType::Review,
            'target' => 3,
            'date' => Carbon::today()->toDateString(),
            'ref' => ['topic_ids' => [$this->topic->id], 'session_id' => null, 'mode' => 'study'],
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.study-plan.tasks.start', [$plan, $reviewTask]))
            ->assertOk()
            ->assertJsonPath('data.attributes.task_type', 'review');

        $this->assertNotNull($reviewTask->refresh()->sessionId());
    }

    public function test_plan_lifecycle_emits_tracking_events(): void
    {
        Event::fake([StudyPlanActivity::class]);

        $plan = $this->createPlan();
        $task = $plan->tasks()->whereDate('date', Carbon::today())->firstOrFail();
        $task->forceFill(['done' => $task->target])->save();

        CompletePlanTaskAction::run($task);

        Event::assertDispatched(
            StudyPlanActivity::class,
            fn (StudyPlanActivity $event) => $event->name === 'study_plan_create',
        );
        Event::assertDispatched(
            StudyPlanActivity::class,
            fn (StudyPlanActivity $event) => $event->name === 'study_plan_task_complete',
        );
    }

    public function test_unsupported_task_types_are_rejected(): void
    {
        $plan = StudyPlan::factory()->for($this->user)->create();
        $task = StudyPlanTask::factory()->for($plan, 'plan')->create(['type' => TaskType::Flashcards]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.study-plan.tasks.start', [$plan, $task]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'task_type_unavailable');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'exam_key' => 'resident',
            'exam_target_date' => Carbon::today()->addDays(7)->toDateString(),
            'daily_goal_questions' => 10,
            'topic_ids' => [$this->topic->id],
            'study_days' => [1, 2, 3, 4, 5, 6, 7],
            'strategy' => 'fixed',
        ];
    }

    private function createPlan(): StudyPlan
    {
        $this->actingAs($this->user, 'sanctum')->postJson(route('api.study-plan.plans.store'), $this->payload());

        return StudyPlan::orderByDesc('id')->firstOrFail();
    }

    private function seedQuestions(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $question = Question::create([
                'stem' => "Câu hỏi #{$i}?",
                'difficulty' => 'medium',
                'status' => QuestionStatus::Published,
                                'is_free' => true,
            ]);

            QuestionOption::create([
                'question_id' => $question->getKey(),
                'label' => 'A',
                'content' => 'Phương án A',
                'is_correct' => true,
                'order' => 0,
            ]);
            QuestionOption::create([
                'question_id' => $question->getKey(),
                'label' => 'B',
                'content' => 'Phương án B',
                'is_correct' => false,
                'order' => 1,
            ]);

            $question->medicalTaxonomyNodes()->sync([$this->topic->id]);
        }
    }
}
