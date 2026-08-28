<?php

declare(strict_types=1);

namespace Modules\Analytics\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\Analytics\Actions\RecalculateDailyLearningStatsAction;
use Modules\Analytics\Models\DailyLearningStat;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Tests\TestCase;

final class DashboardLiveDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_dashboard_replaces_demo_values_with_learner_rollups(): void
    {
        Carbon::setTestNow('2026-08-24 10:00:00');
        $user = User::factory()->create(['name' => 'Nguyễn Minh An']);
        $session = $this->completedSession($user, correctAnswers: 1, answeredQuestions: 2, studySeconds: 1800);

        app(RecalculateDailyLearningStatsAction::class)->handle((int) $user->getKey());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('2')
            ->assertSee('50%')
            ->assertSee('30 phút')
            ->assertSee('1 ngày học liên tục')
            ->assertSee('Hoàn thành '.lcfirst($session->displayName()))
            ->assertDontSee('1.240')
            ->assertDontSee('15 ngày học liên tục');
    }

    public function test_daily_rollup_rebuild_is_idempotent(): void
    {
        $user = User::factory()->create();
        $this->completedSession($user, correctAnswers: 8, answeredQuestions: 10, studySeconds: 600);
        $action = app(RecalculateDailyLearningStatsAction::class);

        $action->handle((int) $user->getKey());
        $action->handle((int) $user->getKey());

        $this->assertDatabaseCount('daily_learning_stats', 1);
        $stat = DailyLearningStat::query()->firstOrFail();
        $this->assertSame(10, $stat->questions_answered);
        $this->assertSame(8, $stat->correct_answers);
        $this->assertSame(600, $stat->study_seconds);
        $this->assertTrue($stat->daily_goal_reached);
    }

    public function test_premium_student_does_not_see_upgrade_banner(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'slug' => 'premium',
            'name' => 'Premium',
            'price_cents' => 199_000,
            'currency' => 'VND',
            'entitlements' => [],
            'features' => [],
            'is_active' => true,
            'sort_order' => 10,
        ]);
        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => 'active',
            'source' => 'test',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Nâng cấp Premium ngay');
    }

    private function completedSession(User $user, int $correctAnswers, int $answeredQuestions, int $studySeconds): QuestionSession
    {
        $questions = collect(range(1, $answeredQuestions))->map(fn (int $index): Question => Question::query()->create([
            'stem' => "Câu hỏi dashboard {$index}",
            'difficulty' => 'medium',
            'status' => QuestionStatus::Published,
            'is_free' => true,
        ]));
        $session = QuestionSession::query()->create([
            'user_id' => $user->getKey(),
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'question_ids' => $questions->pluck('id')->all(),
            'total' => $answeredQuestions,
            'answered_count' => $answeredQuestions,
            'correct_count' => $correctAnswers,
        ]);
        $secondsPerQuestion = intdiv($studySeconds, max(1, $answeredQuestions));

        foreach ($questions as $index => $question) {
            QuestionAttempt::query()->create([
                'session_id' => $session->getKey(),
                'user_id' => $user->getKey(),
                'question_id' => $question->getKey(),
                'selected_option_ids' => [1],
                'is_correct' => $index < $correctAnswers,
                'time_spent_seconds' => $secondsPerQuestion,
                'answered_at' => now(),
            ]);
        }

        return $session->refresh();
    }
}
