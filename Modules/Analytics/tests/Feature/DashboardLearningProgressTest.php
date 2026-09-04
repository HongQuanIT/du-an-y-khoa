<?php

declare(strict_types=1);

namespace Modules\Analytics\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Tests\TestCase;

final class DashboardLearningProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow('2026-08-25 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_progress_only_uses_completed_sessions_of_the_authenticated_student(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();

        $this->createSession($student, Carbon::today()->setTime(9, 0), 10, 8);
        $this->createSession($student, Carbon::today()->setTime(11, 0), 2, 1);
        $this->createSession($student, Carbon::today()->subDay(), 10, 10, SessionStatus::Paused);
        $this->createSession($otherStudent, Carbon::today(), 10, 10);

        $response = $this->actingAs($student)->get(route('dashboard', ['range' => '7d']));

        $response
            ->assertOk()
            ->assertViewHas('progressSummary', [
                'rate' => 75,
                'questions' => 12,
                'correct' => 9,
                'active_days' => 1,
            ])
            ->assertViewHas('headlineStats', [
                'questions' => ['value' => '12', 'delta' => '+12 tuần này'],
                'accuracy' => ['value' => '75%', 'delta' => null],
                'weekly_time' => ['value' => '0 phút', 'delta' => null],
                'streak' => ['value' => '1', 'delta' => null],
            ])
            ->assertViewHas('recentActivities', function (array $activities): bool {
                return count($activities) === 2
                    && $activities[0]['title'] === 'Hoàn thành phiên tùy chỉnh'
                    && str_contains($activities[0]['detail'], 'Đúng 1/2 câu (50%)')
                    && $activities[1]['title'] === 'Hoàn thành phiên tùy chỉnh';
            })
            ->assertViewHas('chartBars', function (array $days): bool {
                $today = collect($days)->firstWhere('date', Carbon::today()->toDateString());

                return count($days) === 7
                    && $today['questions'] === 12
                    && $today['correct'] === 9
                    && $today['rate'] === 75;
            });
    }

    public function test_headline_stats_are_calculated_from_real_learning_activity(): void
    {
        $student = User::factory()->create();
        $currentSession = $this->createSession($student, Carbon::today(), 10, 8);
        $previousSession = $this->createSession($student, Carbon::today()->subWeek(), 10, 5);
        $question = Question::factory()->create();

        QuestionAttempt::factory()->create([
            'session_id' => $currentSession->getKey(),
            'user_id' => $student->getKey(),
            'question_id' => $question->getKey(),
            'time_spent_seconds' => 3900,
            'answered_at' => Carbon::today()->setTime(10, 0),
        ]);
        QuestionAttempt::factory()->create([
            'session_id' => $previousSession->getKey(),
            'user_id' => $student->getKey(),
            'question_id' => $question->getKey(),
            'time_spent_seconds' => 7200,
            'answered_at' => Carbon::today()->subWeek(),
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('headlineStats', [
                'questions' => ['value' => '20', 'delta' => '+10 tuần này'],
                'accuracy' => ['value' => '65%', 'delta' => '+30%'],
                'weekly_time' => ['value' => '1h 5m', 'delta' => null],
                'streak' => ['value' => '1', 'delta' => null],
            ]);
    }

    public function test_progress_range_is_validated_and_limited_to_the_requested_period(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('dashboard', ['range' => '7d']))
            ->assertOk()
            ->assertViewHas('progressRange', '7d')
            ->assertViewHas('chartBars', fn (array $days): bool => count($days) === 7);

        $this->actingAs($student)
            ->get(route('dashboard', ['range' => 'invalid']))
            ->assertOk()
            ->assertViewHas('progressRange', '30d')
            ->assertViewHas('chartBars', fn (array $days): bool => count($days) === 30);
    }

    public function test_progress_uses_semantic_markup_and_exposes_an_accessible_data_table(): void
    {
        $student = User::factory()->create();
        $this->createSession($student, Carbon::today(), 5, 4);

        $this->actingAs($student)
            ->get(route('dashboard', ['range' => '30d']))
            ->assertOk()
            ->assertSee('Tiến trình học tập')
            ->assertSee('aria-current="page"', false)
            ->assertSee('id="student-dashboard-learning-progress"', false)
            ->assertSee('data-admin-dashboard-charts', false)
            ->assertSee('<details', false)
            ->assertSee('<table', false)
            ->assertSee('Dữ liệu chi tiết tiến trình học tập theo ngày')
            ->assertSee('Hoàn thành phiên tùy chỉnh')
            ->assertDontSee('Flashcard Thần kinh');
    }

    private function createSession(
        User $user,
        Carbon $completedAt,
        int $answered,
        int $correct,
        SessionStatus $status = SessionStatus::Completed,
    ): QuestionSession {
        return QuestionSession::factory()->for($user)->create([
            'status' => $status,
            'total' => $answered,
            'answered_count' => $answered,
            'correct_count' => $correct,
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);
    }
}
