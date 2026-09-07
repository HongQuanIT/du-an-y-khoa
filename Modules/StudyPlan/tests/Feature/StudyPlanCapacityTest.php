<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Enums\QuestionScopeType;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\Tag;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanQuestion;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class StudyPlanCapacityTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private MedicalTaxonomyNode $system;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-07 08:00:00');
        $this->user = User::factory()->create();
        $this->system = $this->makeMedicalNode([
            'name' => 'Hệ tim mạch',
            'slug' => 'he-tim-mach',
            'node_type' => 'system',
        ]);

        Question::factory()->count(40)->create([
            'status' => QuestionStatus::Published,
            'is_free' => true,
        ])->each(function (Question $question): void {
            $question->medicalTaxonomyNodes()->sync([$this->system->id]);
            $question->scopes()->create([
                'scope_type' => QuestionScopeType::Exam,
                'scope_key' => 'resident',
            ]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_capacity_shortage_persists_coverage_and_prioritises_high_yield_then_status(): void
    {
        $questions = Question::query()->orderBy('id')->get();
        $highYieldIds = $questions->take(5)->pluck('id')->map('strval')->all();
        $incorrectIds = $questions->slice(5, 3)->pluck('id')->map('strval')->all();
        $tag = Tag::create(['name' => 'High-yield', 'slug' => 'high-yield']);
        $tag->questions()->sync($highYieldIds);

        $session = QuestionSession::factory()->for($this->user)->create();
        foreach ($highYieldIds as $questionId) {
            QuestionAttempt::factory()->correct()->create([
                'session_id' => $session->getKey(),
                'user_id' => $this->user->getKey(),
                'question_id' => $questionId,
                'used_hint' => false,
            ]);
        }
        foreach ($incorrectIds as $questionId) {
            QuestionAttempt::factory()->incorrect()->create([
                'session_id' => $session->getKey(),
                'user_id' => $this->user->getKey(),
                'question_id' => $questionId,
                'used_hint' => false,
            ]);
        }

        $this->actingAs($this->user)
            ->post(route('study-plan.store'), $this->payload(
                endDate: Carbon::tomorrow(),
                hoursPerDay: 0.5,
                studyDays: [1],
            ))
            ->assertRedirect();

        $plan = StudyPlan::firstOrFail();
        $selectedIds = StudyPlanQuestion::query()->pluck('question_id')->map('strval')->all();

        $this->assertSame(40, $plan->total_question_pool);
        $this->assertSame(10, $plan->selected_question_count);
        $this->assertSame(25.0, $plan->coverage_percent);
        $this->assertEqualsCanonicalizing($highYieldIds, array_values(array_intersect($selectedIds, $highYieldIds)));
        $this->assertEqualsCanonicalizing($incorrectIds, array_values(array_intersect($selectedIds, $incorrectIds)));
        $this->assertSame(5, StudyPlanQuestion::query()->where('high_yield_score', 100)->count());
        $this->assertSame(3, StudyPlanQuestion::query()->where('source_status', 'incorrect')->count());
    }

    public function test_full_coverage_uses_the_whole_pool_and_balances_only_selected_weekdays(): void
    {
        $this->actingAs($this->user)
            ->post(route('study-plan.store'), $this->payload(
                endDate: Carbon::parse('2026-09-11'),
                hoursPerDay: 1,
                studyDays: [1, 3, 5],
            ))
            ->assertRedirect();

        $plan = StudyPlan::firstOrFail();
        $days = $plan->days()->orderBy('date')->get();

        $this->assertSame(40, $plan->total_question_pool);
        $this->assertSame(40, $plan->selected_question_count);
        $this->assertSame(100.0, $plan->coverage_percent);
        $this->assertSame(['2026-09-07', '2026-09-09', '2026-09-11'], $days->pluck('date')->map->toDateString()->all());
        $this->assertSame([13, 13, 14], $days->pluck('question_count')->all());
        $this->assertSame(40, StudyPlanQuestion::query()->count());
        $this->assertSame(40, StudyPlanQuestion::query()->distinct()->count('question_id'));
    }

    public function test_server_calculates_questions_per_session_from_selected_hours(): void
    {
        $tomorrowPayload = $this->payload(
            endDate: Carbon::tomorrow(),
            hoursPerDay: 10,
            studyDays: [1, 2],
        );
        $tomorrowPayload['daily_goal_questions'] = 70;

        $this->actingAs($this->user)
            ->post(route('study-plan.store'), $tomorrowPayload)
            ->assertRedirect();

        $this->assertSame(200, StudyPlan::firstOrFail()->daily_goal_questions);

        StudyPlan::query()->delete();

        $halfHourPayload = $this->payload(
            endDate: Carbon::today()->addDays(2),
            hoursPerDay: 0.5,
            studyDays: [1, 2, 3],
        );
        $halfHourPayload['daily_goal_questions'] = 200;

        $this->actingAs($this->user)
            ->post(route('study-plan.store'), $halfHourPayload)
            ->assertRedirect();

        $this->assertSame(10, StudyPlan::firstOrFail()->daily_goal_questions);
    }

    public function test_system_and_discipline_filters_are_combined_with_and_semantics(): void
    {
        $discipline = $this->makeMedicalNode([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-discipline',
            'node_type' => 'specialty',
            'parent_id' => $this->system->id,
        ]);
        Question::query()->orderBy('id')->limit(10)->get()->each(
            fn (Question $question) => $question->medicalTaxonomyNodes()->syncWithoutDetaching([$discipline->id]),
        );

        $payload = $this->payload(
            endDate: Carbon::parse('2026-09-11'),
            hoursPerDay: 1,
            studyDays: [1, 3, 5],
        );
        $payload['system_ids'] = [$this->system->id];
        $payload['discipline_ids'] = [$discipline->id];
        $payload['medical_taxonomy_node_ids'] = [$this->system->id, $discipline->id];

        $this->actingAs($this->user)
            ->post(route('study-plan.store'), $payload)
            ->assertRedirect();

        $plan = StudyPlan::firstOrFail();
        $this->assertSame(10, $plan->total_question_pool);
        $this->assertSame([$this->system->id], $plan->scopeFilters()['system_ids']);
        $this->assertSame([$discipline->id], $plan->scopeFilters()['discipline_ids']);
    }

    public function test_additional_admin_taxonomy_filter_combines_with_the_selected_system(): void
    {
        $disease = $this->makeMedicalNode([
            'name' => 'Bệnh tim thiếu máu cục bộ',
            'slug' => 'benh-tim-thieu-mau-cuc-bo',
            'node_type' => 'disease',
            'parent_id' => $this->system->id,
        ]);
        Question::query()->orderBy('id')->limit(7)->get()->each(
            fn (Question $question) => $question->medicalTaxonomyNodes()->syncWithoutDetaching([$disease->id]),
        );

        $payload = $this->payload(
            endDate: Carbon::parse('2026-09-11'),
            hoursPerDay: 1,
            studyDays: [1, 3, 5],
        );
        $payload['medical_taxonomy_node_ids'] = [$disease->id];

        $this->actingAs($this->user)
            ->post(route('study-plan.store'), $payload)
            ->assertRedirect();

        $plan = StudyPlan::firstOrFail();
        $this->assertSame(7, $plan->total_question_pool);
        $this->assertSame([$disease->id], $plan->scopeFilters()['medical_taxonomy_node_ids']);
        $this->assertSame([$this->system->id], $plan->scopeFilters()['system_ids']);
    }

    public function test_empty_system_and_discipline_filters_use_every_question_in_the_exam(): void
    {
        $payload = $this->payload(
            endDate: Carbon::parse('2026-09-11'),
            hoursPerDay: 1,
            studyDays: [1, 3, 5],
        );
        unset($payload['medical_taxonomy_node_ids'], $payload['system_ids']);

        $this->actingAs($this->user)
            ->post(route('study-plan.store'), $payload)
            ->assertRedirect();

        $this->assertSame(40, StudyPlan::firstOrFail()->total_question_pool);
    }

    public function test_question_status_filter_excludes_unselected_latest_results(): void
    {
        $incorrectIds = Question::query()->orderBy('id')->limit(2)->pluck('id')->map('strval')->all();
        $session = QuestionSession::factory()->for($this->user)->create();
        foreach ($incorrectIds as $questionId) {
            QuestionAttempt::factory()->incorrect()->create([
                'session_id' => $session->getKey(),
                'user_id' => $this->user->getKey(),
                'question_id' => $questionId,
            ]);
        }

        $payload = $this->payload(
            endDate: Carbon::parse('2026-09-11'),
            hoursPerDay: 1,
            studyDays: [1, 3, 5],
        );
        $payload['question_statuses'] = ['incorrect'];

        $this->actingAs($this->user)
            ->post(route('study-plan.store'), $payload)
            ->assertRedirect();

        $plan = StudyPlan::firstOrFail();
        $this->assertSame(2, $plan->total_question_pool);
        $this->assertEqualsCanonicalizing(
            $incorrectIds,
            StudyPlanQuestion::query()->pluck('question_id')->map('strval')->all(),
        );
    }

    /** @return array<string, mixed> */
    private function payload(Carbon $endDate, float $hoursPerDay, array $studyDays): array
    {
        return [
            'exam_key' => 'resident',
            'exam_target_date' => $endDate->toDateString(),
            'hours_per_day' => $hoursPerDay,
            'medical_taxonomy_node_ids' => [$this->system->id],
            'system_ids' => [$this->system->id],
            'study_days' => $studyDays,
            'question_statuses' => ['unanswered', 'correct_with_hints', 'incorrect', 'correct'],
            'strategy' => 'fixed',
        ];
    }
}
