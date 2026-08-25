<?php

declare(strict_types=1);

namespace Modules\Analytics\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Analytics\Actions\RecalculateTopicMasteryAction;
use Modules\Analytics\Models\TopicMastery;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

/**
 * Phase 3: the dashboard reads real plan tasks, weak topics and resume state.
 */
final class DashboardStudyPlanTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private MedicalTaxonomyNode $topic;

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
    }

    public function test_dashboard_shows_todays_plan_task(): void
    {
        $plan = StudyPlan::factory()->for($this->user)->create(['topic_scope' => [$this->topic->id]]);
        StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => Carbon::today()->toDateString(),
            'target' => 15,
            'ref' => ['topic_ids' => [$this->topic->id], 'session_id' => null, 'mode' => 'study'],
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Làm 15 câu Tim mạch')
            ->assertSee('Nhiệm vụ hôm nay');
    }

    public function test_continue_learning_prefers_an_unfinished_session(): void
    {
        $plan = StudyPlan::factory()->for($this->user)->create(['topic_scope' => [$this->topic->id]]);
        $task = StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => Carbon::today()->toDateString(),
            'target' => 10,
            'ref' => ['topic_ids' => [$this->topic->id], 'session_id' => null, 'mode' => 'study'],
        ]);

        $session = QuestionSession::create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Paused,
            'filters' => ['study_plan_task_id' => $task->id],
            'question_ids' => [],
            'total' => 10,
            'answered_count' => 4,
        ]);

        $task->forceFill(['ref' => array_merge($task->ref, ['session_id' => $session->getKey()])])->save();

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Đang học dở')
            ->assertSee('Câu 5/10');
    }

    public function test_weak_topics_come_from_the_mastery_rollup(): void
    {
        $this->recordAttempts(correct: 1, incorrect: 1);

        app(RecalculateTopicMasteryAction::class)->handle($this->user->id);

        $mastery = TopicMastery::firstOrFail();
        $this->assertSame(50.0, $mastery->correct_rate);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tim mạch')
            ->assertSee('50%');

        UserQuestionStatusModel::query()
            ->where('user_id', $this->user->id)
            ->where('status', UserQuestionStatus::Incorrect)
            ->update([
                'status' => UserQuestionStatus::Correct->value,
                'last_correct_at' => Carbon::now(),
            ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Tim mạch');
    }

    private function recordAttempts(int $correct, int $incorrect): void
    {
        $session = QuestionSession::create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'question_ids' => [],
            'total' => $correct + $incorrect,
            'answered_count' => $correct + $incorrect,
        ]);

        for ($i = 0; $i < $correct + $incorrect; $i++) {
            $question = Question::create([
                'stem' => "Câu hỏi #{$i}?",
                'difficulty' => 'medium',
                'status' => QuestionStatus::Published,
                'is_free' => true,
            ]);
            $question->medicalTaxonomyNodes()->sync([$this->topic->id]);

            QuestionAttempt::create([
                'session_id' => $session->getKey(),
                'user_id' => $this->user->id,
                'question_id' => $question->getKey(),
                'selected_option_ids' => [],
                'is_correct' => $i < $correct,
                'time_spent_seconds' => 30,
                'answered_at' => Carbon::now(),
            ]);
            UserQuestionStatusModel::query()->create([
                'user_id' => $this->user->id,
                'question_id' => $question->getKey(),
                'status' => $i < $correct ? UserQuestionStatus::Correct : UserQuestionStatus::Incorrect,
                'attempts_count' => 1,
                'last_attempt_at' => Carbon::now(),
                'last_correct_at' => $i < $correct ? Carbon::now() : null,
            ]);
        }
    }
}
