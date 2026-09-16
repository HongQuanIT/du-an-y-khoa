<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

/**
 * Phase 2: viewing and progressing through a generated plan.
 */
final class StudyPlanScheduleTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private Lesson $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);
        $this->topic = $this->makeLesson([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach',
            'sort_order' => 0,
        ]);
        $questions = Question::factory()->count(5)->create([
            'status' => QuestionStatus::Published,
            'is_free' => true,
        ]);
        foreach ($questions as $question) {
            $question->lessons()->sync([$this->topic->id]);
        }
    }

    public function test_overdue_tasks_are_marked_skipped_automatically(): void
    {
        $plan = $this->createPlan();
        $task = $this->firstTask($plan);
        $task->forceFill(['date' => Carbon::yesterday()->toDateString()])->save();

        $this->actingAs($this->user)
            ->get(route('study-plan.detail', $plan))
            ->assertOk()
            ->assertSee('Bắt đầu');

        $this->assertSame(TaskStatus::Skipped, $task->refresh()->status);
    }

    public function test_skipped_tasks_can_be_started_or_continued(): void
    {
        $plan = $this->createPlan();
        $task = $this->firstTask($plan);
        $task->forceFill(['status' => TaskStatus::Skipped])->save();

        $this->actingAs($this->user)
            ->get(route('study-plan.detail', $plan))
            ->assertOk()
            ->assertSee('Bắt đầu');
    }

    public function test_web_edit_routes_are_removed(): void
    {
        $plan = $this->createPlan();

        $this->actingAs($this->user)
            ->get('/study-plan/'.$plan->getKey().'/edit')
            ->assertNotFound();

        $this->actingAs($this->user)
            ->put('/study-plan/'.$plan->getKey(), [
                'exam_key' => 'usmle',
                'exam_target_date' => Carbon::today()->addDays(6)->toDateString(),
                'daily_goal_questions' => 30,
                'lesson_ids' => [$this->topic->id],
                'study_days' => [1, 2, 3, 4, 5, 6, 7],
                'strategy' => 'fixed',
            ])
            ->assertMethodNotAllowed();

        $this->actingAs($this->user)
            ->get(route('study-plan.detail', $plan))
            ->assertOk()
            ->assertDontSee('Chỉnh sửa', false);
    }

    public function test_learners_cannot_delete_plans_on_the_web(): void
    {
        $plan = $this->createPlan();

        $this->actingAs($this->user)
            ->delete('/study-plan/'.$plan->getKey())
            ->assertMethodNotAllowed();

        $this->actingAs($this->user)
            ->get(route('study-plan.detail', $plan))
            ->assertOk()
            ->assertDontSee('Xóa kế hoạch', false)
            ->assertDontSee('>Xóa<', false);

        $this->assertSame(1, StudyPlan::count());
    }

    public function test_creating_a_second_plan_pauses_the_first(): void
    {
        $first = $this->createPlan();
        $second = $this->createPlan();

        $this->assertFalse($first->refresh()->isActive());
        $this->assertTrue($second->isActive());
    }

    private function createPlan(): StudyPlan
    {
        $this->actingAs($this->user)->post(route('study-plan.store'), [
            'exam_key' => 'resident',
            'exam_target_date' => Carbon::today()->addDays(10)->toDateString(),
            'daily_goal_questions' => 10,
            'lesson_ids' => [$this->topic->id],
            'study_days' => [1, 2, 3, 4, 5, 6, 7],
            'strategy' => 'fixed',
        ]);

        return StudyPlan::orderByDesc('id')->firstOrFail();
    }

    private function firstTask(StudyPlan $plan): StudyPlanTask
    {
        return $plan->tasks()->orderBy('date')->firstOrFail();
    }
}
