<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Analytics\Models\TopicMastery;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Jobs\ReplanActivePlansJob;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;
use Tests\TestCase;
use Tests\Support\CreatesMedicalTaxonomy;


/**
 * Phase 4: the nightly job folds missed days into upcoming ones and steers
 * adaptive plans towards weak topics.
 */
final class AdaptiveReplanTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_missed_tasks_move_to_an_upcoming_day(): void
    {
        $plan = StudyPlan::factory()->adaptive()->for($this->user)->create([
            'daily_goal_questions' => 20,
        ]);

        $missed = StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => Carbon::today()->subDays(2)->toDateString(),
            'target' => 20,
        ]);

        StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => Carbon::tomorrow()->toDateString(),
            'target' => 20,
        ]);

        ReplanActivePlansJob::dispatchSync();

        $this->assertTrue($missed->refresh()->date->isSameDay(Carbon::tomorrow()));
        $this->assertNotNull($plan->refresh()->replanned_at);
        $this->assertTrue($plan->wasRecentlyReplanned());
    }

    public function test_missed_tasks_are_skipped_when_no_day_has_room(): void
    {
        $plan = StudyPlan::factory()->adaptive()->for($this->user)->create([
            'daily_goal_questions' => 20,
        ]);

        $missed = StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => Carbon::yesterday()->toDateString(),
            'target' => 20,
        ]);

        StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => Carbon::tomorrow()->toDateString(),
            'target' => 40,
        ]);

        ReplanActivePlansJob::dispatchSync();

        $this->assertSame(TaskStatus::Skipped, $missed->refresh()->status);
    }

    public function test_upcoming_tasks_target_the_weakest_topic(): void
    {
        $strong = $this->makeMedicalNode(['name' => 'Hô hấp', 'slug' => 'ho-hap', 'node_type' => 'system', 'sort_order' => 0]);
        $weak = $this->makeMedicalNode(['name' => 'Tim mạch', 'slug' => 'tim-mach', 'node_type' => 'system', 'sort_order' => 1]);

        $plan = StudyPlan::factory()->adaptive()->for($this->user)->create([
            'topic_scope' => [$strong->id, $weak->id],
        ]);

        $upcoming = StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => Carbon::tomorrow()->toDateString(),
            'ref' => ['topic_ids' => [$strong->id], 'session_id' => null, 'mode' => 'study'],
        ]);

        TopicMastery::create([
            'user_id' => $this->user->id,
            'medical_taxonomy_node_id' => $strong->id,
            'attempts' => 10,
            'correct' => 9,
            'correct_rate' => 90,
            'mastery_level' => 5,
        ]);
        TopicMastery::create([
            'user_id' => $this->user->id,
            'medical_taxonomy_node_id' => $weak->id,
            'attempts' => 10,
            'correct' => 3,
            'correct_rate' => 30,
            'mastery_level' => 1,
        ]);

        ReplanActivePlansJob::dispatchSync();

        $this->assertSame([$weak->id], $upcoming->refresh()->topicIds());
    }

    public function test_fixed_plans_are_left_alone(): void
    {
        $plan = StudyPlan::factory()->for($this->user)->create();

        $missed = StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => Carbon::yesterday()->toDateString(),
        ]);

        ReplanActivePlansJob::dispatchSync();

        $this->assertTrue($missed->refresh()->date->isSameDay(Carbon::yesterday()));
        $this->assertNull($plan->refresh()->replanned_at);
    }
}
