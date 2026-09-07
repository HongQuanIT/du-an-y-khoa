<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\Tag;
use Modules\QuestionBank\Services\QuestionLearningState;
use Modules\QuestionBank\Services\SessionQuestionSelector;
use Tests\TestCase;

final class AdaptiveLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_answer_results_advance_mastery_and_spaced_repetition_state(): void
    {
        Carbon::setTestNow('2026-09-06 08:00:00');
        $user = User::factory()->create();
        $question = Question::factory()->free()->create();
        $learningState = app(QuestionLearningState::class);

        $first = $learningState->record(
            $user->id,
            $question,
            UserQuestionStatus::Correct,
            now(),
            true,
        );

        $this->assertSame(1, $first->correct_streak);
        $this->assertSame(18, $first->mastery_score);
        $this->assertSame(3, $first->review_interval_days);
        $this->assertTrue($first->next_review_at->equalTo(now()->addDays(3)));

        Carbon::setTestNow('2026-09-09 08:00:00');
        $second = $learningState->record(
            $user->id,
            $question,
            UserQuestionStatus::Correct,
            now(),
            true,
        );

        $this->assertSame(2, $second->correct_streak);
        $this->assertSame(36, $second->mastery_score);
        $this->assertSame(7, $second->review_interval_days);

        Carbon::setTestNow('2026-09-10 08:00:00');
        $incorrect = $learningState->record(
            $user->id,
            $question,
            UserQuestionStatus::Incorrect,
            now(),
            true,
        );

        $this->assertSame(0, $incorrect->correct_streak);
        $this->assertSame(11, $incorrect->mastery_score);
        $this->assertSame(1, $incorrect->incorrect_count);
        $this->assertSame(1, $incorrect->review_interval_days);
        $this->assertTrue($incorrect->next_review_at->equalTo(now()->addDay()));
    }

    public function test_question_bank_prioritises_reviews_but_still_fills_the_requested_published_pool(): void
    {
        Carbon::setTestNow('2026-09-06 08:00:00');
        $user = User::factory()->create();
        $questions = Question::factory()->free()->count(6)->create([
            'status' => QuestionStatus::Published,
        ]);
        $historySession = QuestionSession::factory()->for($user)->create();
        $learningState = app(QuestionLearningState::class);

        $incorrect = $questions[0];
        $due = $questions[1];
        $notDue = $questions[2];

        $this->attempt($historySession, $user, $incorrect, false, now()->subDay());
        $learningState->record(
            $user->id,
            $incorrect,
            UserQuestionStatus::Incorrect,
            now()->subDay(),
            true,
        );

        $this->attempt($historySession, $user, $due, true, now()->subDays(5));
        $learningState->record(
            $user->id,
            $due,
            UserQuestionStatus::Correct,
            now()->subDays(5),
            true,
        );

        $this->attempt($historySession, $user, $notDue, true, now());
        $learningState->record(
            $user->id,
            $notDue,
            UserQuestionStatus::Correct,
            now(),
            true,
        );

        $selected = app(SessionQuestionSelector::class)->forSession(
            $user,
            new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::Custom,
                count: 6,
            ),
        );

        $this->assertCount(6, $selected);
        $this->assertContains((string) $incorrect->getKey(), $selected);
        $this->assertContains((string) $due->getKey(), $selected);
        $this->assertContains((string) $notDue->getKey(), $selected);
    }

    public function test_coverage_quota_keeps_most_of_a_partial_session_for_unseen_questions(): void
    {
        $user = User::factory()->create();
        $questions = Question::factory()->free()->count(10)->create([
            'status' => QuestionStatus::Published,
        ]);
        $historySession = QuestionSession::factory()->for($user)->create([
            'status' => SessionStatus::Completed,
            'updated_at' => now()->subDays(10),
        ]);

        foreach ($questions->take(4) as $index => $question) {
            $this->attempt($historySession, $user, $question, $index >= 2, now()->subDays(10));
        }

        $selected = app(SessionQuestionSelector::class)->forSession(
            $user,
            new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::Custom,
                count: 5,
            ),
        );

        $unseenIds = $questions->slice(4)->pluck('id')->map('strval');
        $this->assertCount(5, $selected);
        $this->assertGreaterThanOrEqual(3, collect($selected)->intersect($unseenIds)->count());
    }

    public function test_session_cooldown_avoids_a_recent_correct_answer_unless_needed_to_fill(): void
    {
        $user = User::factory()->create();
        $questions = Question::factory()->free()->count(4)->create([
            'status' => QuestionStatus::Published,
        ]);
        $recent = QuestionSession::factory()->for($user)->create([
            'status' => SessionStatus::Completed,
        ]);
        $this->attempt($recent, $user, $questions[0], true, now());
        app(QuestionLearningState::class)->record(
            $user->id,
            $questions[0],
            UserQuestionStatus::Correct,
            now(),
            true,
        );
        $selector = app(SessionQuestionSelector::class);
        $base = fn (int $count): CreateSessionData => new CreateSessionData(
            mode: SessionMode::Study,
            source: SessionSource::Custom,
            count: $count,
        );

        $partial = $selector->forSession($user, $base(2));
        $full = $selector->forSession($user, $base(4));

        $this->assertNotContains((string) $questions[0]->getKey(), $partial);
        $this->assertContains((string) $questions[0]->getKey(), $full);
        $this->assertCount(4, $full);
    }

    public function test_question_bank_prioritises_high_yield_within_the_adaptive_pool(): void
    {
        $user = User::factory()->create();
        $questions = Question::factory()->free()->count(2)->create([
            'status' => QuestionStatus::Published,
        ]);
        $tag = Tag::create(['name' => 'High-yield', 'slug' => 'high-yield']);
        $questions[1]->tags()->attach($tag);

        $selected = app(SessionQuestionSelector::class)->forSession(
            $user,
            new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::Custom,
                count: 1,
            ),
        );

        $this->assertSame([(string) $questions[1]->getKey()], $selected);
    }

    private function attempt(
        QuestionSession $session,
        User $user,
        Question $question,
        bool $correct,
        Carbon $answeredAt,
    ): void {
        QuestionAttempt::factory()->create([
            'session_id' => $session->getKey(),
            'user_id' => $user->getKey(),
            'question_id' => $question->getKey(),
            'is_correct' => $correct,
            'used_hint' => false,
            'answered_at' => $answeredAt,
        ]);
    }
}
