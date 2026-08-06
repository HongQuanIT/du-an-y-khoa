<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\Topic;
use Modules\StudyPlan\Actions\CompletePlanTaskAction;
use Modules\StudyPlan\Enums\PlanStatus;
use Modules\StudyPlan\Enums\TaskStatus;
use Modules\StudyPlan\Enums\TaskType;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

/**
 * The Phase 1 vertical slice: create a plan, see today's task, answer its
 * questions and watch the task close itself.
 */
final class StudyPlanFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::findOrCreate(Role::Student->value, 'web');

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);

        $this->topic = Topic::create([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach',
            'type' => 'system',
            'order' => 0,
        ]);

        $this->seedQuestions(12);
    }

    public function test_wizard_creates_a_plan_with_daily_tasks(): void
    {
        $response = $this->actingAs($this->user)->post(route('study-plan.store'), $this->wizardPayload());

        $plan = StudyPlan::firstOrFail();

        $response->assertRedirect(route('study-plan.detail', $plan));

        $this->assertSame($this->user->id, $plan->user_id);
        $this->assertSame(5, $plan->daily_goal_questions);
        $this->assertSame([$this->topic->id], $plan->scopeTopicIds());
        $this->assertTrue($plan->tasks()->where('type', TaskType::Questions)->exists());
        $this->assertTrue($plan->tasks()->whereDate('date', Carbon::today())->exists());
    }

    public function test_plan_session_combines_multiple_difficulty_levels(): void
    {
        $questions = Question::query()->orderBy('id')->limit(2)->get();
        $questions[0]->update(['difficulty' => 'very_easy']);
        $questions[1]->update(['difficulty' => 'very_hard']);
        $payload = array_merge($this->wizardPayload(), [
            'difficulties' => ['very_easy', 'very_hard'],
        ]);

        $this->actingAs($this->user)
            ->post(route('study-plan.store'), $payload)
            ->assertRedirect();

        $plan = StudyPlan::firstOrFail();
        $this->assertSame(['very_easy', 'very_hard'], $plan->scopeFilters()['difficulties']);
        $task = $this->todayTask($plan);

        $this->actingAs($this->user)
            ->post(route('study-plan.tasks.start', [$plan, $task]))
            ->assertRedirect(route('study-plan.session', [$plan, $task]));

        $session = QuestionSession::firstOrFail();
        $selectedDifficulties = Question::query()
            ->whereIn('id', $session->question_ids)
            ->get()
            ->pluck('difficulty')
            ->map(fn ($difficulty): string => $difficulty->value)
            ->unique()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(['very_easy', 'very_hard'], $selectedDifficulties);
        $this->assertCount(2, $session->question_ids);
    }

    public function test_wizard_rejects_a_past_exam_date(): void
    {
        $payload = array_merge($this->wizardPayload(), [
            'exam_target_date' => Carbon::yesterday()->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->post(route('study-plan.store'), $payload)
            ->assertSessionHasErrors('exam_target_date');

        $this->assertSame(0, StudyPlan::count());
    }

    public function test_overview_lists_todays_task(): void
    {
        $plan = $this->createPlan();

        $this->actingAs($this->user)
            ->get(route('study-plan.index'))
            ->assertOk()
            ->assertSee($plan->name)
            ->assertSee('Làm 5 câu Tim mạch')
            ->assertSee('Các lộ trình của bạn');
    }

    public function test_overview_keeps_every_created_plan_visible(): void
    {
        $first = $this->createPlan();
        $second = $this->createPlan();

        $this->actingAs($this->user)
            ->get(route('study-plan.index'))
            ->assertOk()
            ->assertSee($first->name, false)
            ->assertSee($second->name, false)
            ->assertSee('Đang học')
            ->assertSee('Tạm dừng');
    }

    public function test_overview_paginates_when_learner_has_many_plans(): void
    {
        foreach (range(1, 7) as $index) {
            StudyPlan::factory()->for($this->user)->paused()->create([
                'name' => "Lộ trình số {$index}",
                'status' => PlanStatus::Paused,
                'created_at' => Carbon::today()->addSeconds($index),
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('study-plan.index'))
            ->assertOk()
            ->assertSee('Bạn đang có 7 kế hoạch')
            ->assertSee('Lộ trình số 7')
            ->assertDontSee('Lộ trình số 1')
            ->assertSee('Trang 1/2')
            ->assertSee('Phân trang lộ trình', false)
            ->assertSee('page=2', false);

        $this->actingAs($this->user)
            ->get(route('study-plan.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Lộ trình số 1')
            ->assertDontSee('Lộ trình số 7')
            ->assertSee('Trang 2/2');
    }

    public function test_starting_a_task_opens_a_study_plan_session(): void
    {
        $plan = $this->createPlan();
        $task = $this->todayTask($plan);

        $this->actingAs($this->user)
            ->post(route('study-plan.tasks.start', [$plan, $task]))
            ->assertRedirect(route('study-plan.session', [$plan, $task]));

        $session = QuestionSession::firstOrFail();

        $this->assertSame(SessionSource::StudyPlan, $session->source);
        $this->assertSame($session->getKey(), $task->refresh()->sessionId());
        $this->assertCount(5, $session->question_ids);
        $this->assertSame(5, $session->snapshots()->count());
    }

    public function test_answering_every_question_completes_the_task(): void
    {
        $plan = $this->createPlan();
        $task = $this->todayTask($plan);

        $this->actingAs($this->user)->post(route('study-plan.tasks.start', [$plan, $task]));

        $session = QuestionSession::firstOrFail();

        foreach ($session->question_ids as $index => $questionId) {
            $question = Question::with('options')->findOrFail($questionId);

            $this->actingAs($this->user)->post(route('study-plan.session.answer', [$plan, $task]), [
                'question_id' => $question->getKey(),
                'option_ids' => [$question->options->firstWhere('is_correct', true)->id],
                'index' => $index,
            ]);
        }

        $task->refresh();

        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertSame(5, $task->done);
        $this->assertSame(5, $plan->refresh()->questionsDone());

        $this->actingAs($this->user)
            ->get(route('study-plan.session.summary', [$plan, $task]))
            ->assertOk()
            ->assertSee('Phân tích kết quả')
            ->assertSee('Xem lại từng câu')
            ->assertSee('Tỷ lệ đúng theo chủ đề')
            ->assertSee('data-testid="topic-accuracy-chart-scroll"', false);

        $this->actingAs($this->user)
            ->get(route('study-plan.session.review', [$plan, $task]))
            ->assertOk()
            ->assertSee('Xem lại câu hỏi')
            ->assertSee('Q1')
            ->assertSee('Giải thích chi tiết', false);
    }

    public function test_question_map_can_open_earlier_questions_after_finishing(): void
    {
        $plan = $this->createPlan();
        $task = $this->todayTask($plan);

        $this->actingAs($this->user)->post(route('study-plan.tasks.start', [$plan, $task]));

        $session = QuestionSession::firstOrFail();
        $questionIds = $session->question_ids;

        foreach ($questionIds as $index => $questionId) {
            $question = Question::with('options')->findOrFail($questionId);

            $this->actingAs($this->user)->post(route('study-plan.session.answer', [$plan, $task]), [
                'question_id' => $question->getKey(),
                'option_ids' => [$question->options->firstWhere('is_correct', true)->id],
                'index' => $index,
            ]);
        }

        $this->assertTrue($task->refresh()->isDone());

        $firstQuestion = Question::findOrFail($questionIds[0]);

        $this->actingAs($this->user)
            ->get(route('study-plan.session', [$plan, $task, 'index' => 0]))
            ->assertOk()
            ->assertSee($firstQuestion->stem, false)
            ->assertDontSee('Phân tích kết quả');

        $this->actingAs($this->user)
            ->get(route('study-plan.session', [$plan, $task]))
            ->assertRedirect(route('study-plan.session.summary', [$plan, $task]));
    }

    public function test_summary_needs_review_links_to_filtered_review(): void
    {
        $plan = $this->createPlan();
        $task = $this->todayTask($plan);

        $this->actingAs($this->user)->post(route('study-plan.tasks.start', [$plan, $task]));

        $session = QuestionSession::firstOrFail();

        foreach ($session->question_ids as $index => $questionId) {
            $question = Question::with('options')->findOrFail($questionId);
            $wrong = $question->options->firstWhere('is_correct', false);

            $this->actingAs($this->user)->post(route('study-plan.session.answer', [$plan, $task]), [
                'question_id' => $question->getKey(),
                'option_ids' => [$wrong->id],
                'index' => $index,
            ]);
        }

        $reviewUrl = route('study-plan.session.review', [
            $plan,
            $task,
            'filter' => 'needs',
            'topic' => $this->topic->name,
        ]);

        $this->actingAs($this->user)
            ->get(route('study-plan.session.summary', [$plan, $task]))
            ->assertOk()
            ->assertSee('Cần ôn lại')
            ->assertSee('filter=needs', false)
            ->assertSee(rawurlencode($this->topic->name), false);

        $this->actingAs($this->user)
            ->get($reviewUrl)
            ->assertOk()
            ->assertSee('Cần ôn')
            ->assertSee("filter: 'needs'", false)
            ->assertSee("topic: 'Tim mạch'", false);
    }

    public function test_flagging_a_question_persists_into_review(): void
    {
        $plan = $this->createPlan();
        $task = $this->todayTask($plan);

        $this->actingAs($this->user)->post(route('study-plan.tasks.start', [$plan, $task]));

        $session = QuestionSession::firstOrFail();
        $questionId = $session->question_ids[0];

        $this->actingAs($this->user)
            ->postJson(route('study-plan.session.annotate', [$plan, $task]), [
                'question_id' => $questionId,
                'flagged' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.flagged', true);

        $this->assertTrue((bool) ($session->refresh()->annotations[$questionId]['flagged'] ?? false));

        foreach ($session->question_ids as $index => $id) {
            $q = Question::with('options')->findOrFail($id);
            $this->actingAs($this->user)->post(route('study-plan.session.answer', [$plan, $task]), [
                'question_id' => $q->getKey(),
                'option_ids' => [$q->options->firstWhere('is_correct', true)->id],
                'index' => $index,
            ]);
        }

        $this->assertTrue(
            (bool) QuestionAttempt::query()
                ->where('session_id', $session->getKey())
                ->where('question_id', $questionId)
                ->value('flagged')
        );

        $this->actingAs($this->user)
            ->get(route('study-plan.session.review', [$plan, $task]))
            ->assertOk()
            ->assertSee('Gắn cờ', false)
            ->assertSee('text-amber-600', false);
    }

    public function test_session_notes_and_highlights_persist_into_review(): void
    {
        $plan = $this->createPlan();
        $task = $this->todayTask($plan);

        $this->actingAs($this->user)->post(route('study-plan.tasks.start', [$plan, $task]));

        $session = QuestionSession::firstOrFail();
        $questionId = $session->question_ids[0];
        $question = Question::findOrFail($questionId);
        $stemHtml = '<mark class="rounded-sm" style="background-color: rgba(239, 68, 68, 0.3)">'.e($question->stem).'</mark>';

        $this->actingAs($this->user)
            ->postJson(route('study-plan.session.annotate', [$plan, $task]), [
                'question_id' => $questionId,
                'note' => 'Nhớ cơ chế adenosine cắt vòng vào lại.',
                'stem_html' => $stemHtml,
            ])
            ->assertOk()
            ->assertJsonPath('data.note', 'Nhớ cơ chế adenosine cắt vòng vào lại.')
            ->assertJsonPath('data.stem_html', '<mark class="rounded-sm" data-hl="#EF4444" style="background-color: #EF44444D">'.e($question->stem).'</mark>');

        $this->assertSame(
            'Nhớ cơ chế adenosine cắt vòng vào lại.',
            $session->refresh()->annotations[$questionId]['note'] ?? null,
        );

        foreach ($session->question_ids as $index => $id) {
            $q = Question::with('options')->findOrFail($id);
            $this->actingAs($this->user)->post(route('study-plan.session.answer', [$plan, $task]), [
                'question_id' => $q->getKey(),
                'option_ids' => [$q->options->firstWhere('is_correct', true)->id],
                'index' => $index,
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('study-plan.session.review', [$plan, $task]))
            ->assertOk()
            ->assertSee('Nhớ cơ chế adenosine cắt vòng vào lại.', false)
            ->assertSee('#EF4444', false)
            ->assertSee('#EF44444D', false)
            ->assertSee('Xem ghi chú', false);
    }

    public function test_completing_a_task_twice_does_not_double_progress(): void
    {
        $plan = $this->createPlan();
        $task = $this->todayTask($plan);
        $task->forceFill(['done' => $task->target])->save();

        CompletePlanTaskAction::run($task);
        CompletePlanTaskAction::run($task->refresh());

        $this->assertSame(5, $task->refresh()->done);
        $this->assertSame(1, $plan->refresh()->progress_cache['tasks_done']);
    }

    public function test_a_task_cannot_be_marked_done_before_reaching_the_target(): void
    {
        $plan = $this->createPlan();
        $task = $this->todayTask($plan);

        CompletePlanTaskAction::run($task);

        $this->assertSame(TaskStatus::Pending, $task->refresh()->status);
        $this->assertSame(0, $task->done);
    }

    public function test_a_plan_cannot_be_opened_by_another_learner(): void
    {
        $plan = $this->createPlan();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('study-plan.detail', $plan))
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardPayload(): array
    {
        return [
            'exam_key' => 'resident',
            'exam_target_date' => Carbon::today()->addDays(10)->toDateString(),
            'daily_goal_questions' => 5,
            'topic_ids' => [$this->topic->id],
            'study_days' => [1, 2, 3, 4, 5, 6, 7],
            'strategy' => 'fixed',
        ];
    }

    private function createPlan(): StudyPlan
    {
        $this->actingAs($this->user)->post(route('study-plan.store'), $this->wizardPayload());

        return StudyPlan::firstOrFail();
    }

    private function todayTask(StudyPlan $plan): StudyPlanTask
    {
        return $plan->tasks()
            ->whereDate('date', Carbon::today())
            ->where('type', TaskType::Questions)
            ->firstOrFail();
    }

    private function seedQuestions(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $question = Question::create([
                'stem' => "Câu hỏi kiểm thử #{$i}?",
                'explanation' => 'Giải thích.',
                'difficulty' => 'medium',
                'status' => QuestionStatus::Published,
                'topic_id' => $this->topic->id,
                'is_free' => true,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $index => $label) {
                QuestionOption::create([
                    'question_id' => $question->getKey(),
                    'label' => $label,
                    'content' => "Phương án {$label}",
                    'is_correct' => $index === 0,
                    'order' => $index,
                ]);
            }
        }
    }
}
