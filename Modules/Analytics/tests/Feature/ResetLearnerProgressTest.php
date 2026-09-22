<?php

declare(strict_types=1);

namespace Modules\Analytics\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\AiAssistant\Models\AiThread;
use Modules\AiAssistant\Models\AiUsage;
use Modules\Analytics\Actions\RecalculateDailyLearningStatsAction;
use Modules\Analytics\Actions\ResetLearnerProgressAction;
use Modules\Analytics\Models\DailyLearningStat;
use Modules\Analytics\Models\TopicMastery;
use Modules\Analytics\Support\DashboardCache;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;
use Modules\Personalization\Models\Bookmark;
use Modules\Personalization\Models\BookmarkFolder;
use Modules\QuestionBank\Actions\SyncQuestionStatsAction;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class ResetLearnerProgressTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Cache::flush();
        Carbon::setTestNow('2026-09-22 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_danger_tab_renders_reset_form(): void
    {
        $user = $this->student();

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'reset-alt']))
            ->assertOk()
            ->assertSee('Reset thống kê')
            ->assertSee('RESET')
            ->assertSee(route('profile.reset-progress'), false);
    }

    public function test_reset_wipes_learning_keeps_billing_classroom_and_feedback(): void
    {
        $user = $this->student(['study_objective' => 'nlyt']);
        $other = $this->student();

        $question = Question::query()->create([
            'stem' => 'Câu reset progress',
            'difficulty' => 'medium',
            'status' => QuestionStatus::Published,
            'is_free' => true,
        ]);
        $otherQuestion = Question::query()->create([
            'stem' => 'Câu của học viên khác',
            'difficulty' => 'medium',
            'status' => QuestionStatus::Published,
            'is_free' => true,
        ]);

        $session = $this->seedCompletedSession($user, $question, correct: true);
        $this->seedCompletedSession($other, $otherQuestion, correct: true);

        UserQuestionStatusModel::query()->create([
            'user_id' => $user->getKey(),
            'question_id' => $question->getKey(),
            'status' => UserQuestionStatus::Correct,
            'attempts_count' => 1,
            'correct_count' => 1,
            'last_served_session_id' => $session->getKey(),
        ]);

        app(RecalculateDailyLearningStatsAction::class)->handle((int) $user->getKey());
        $lesson = $this->makeLesson(['name' => 'Nội tiết', 'slug' => 'noi-tiet-reset']);
        TopicMastery::query()->create([
            'user_id' => $user->getKey(),
            'lesson_id' => $lesson->getKey(),
            'attempts' => 5,
            'correct' => 2,
            'correct_rate' => 40.0,
            'mastery_level' => 1,
            'last_activity_at' => now(),
        ]);

        $plan = StudyPlan::factory()->for($user)->create();
        StudyPlanTask::factory()->for($plan, 'plan')->create([
            'date' => Carbon::today()->toDateString(),
            'target' => 10,
        ]);

        Bookmark::query()->create([
            'user_id' => $user->getKey(),
            'bookmarkable_type' => Bookmark::TYPE_QUESTION,
            'bookmarkable_id' => $question->getKey(),
        ]);
        BookmarkFolder::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'Ôn tim mạch',
        ]);

        AiThread::query()->create([
            'user_id' => $user->getKey(),
            'title' => 'Giải thích STEMI',
            'context_type' => 'question',
            'context_id' => $question->getKey(),
        ]);
        AiUsage::query()->create([
            'user_id' => $user->getKey(),
            'date' => Carbon::today()->toDateString(),
            'count' => 3,
        ]);

        $feedback = QuestionFeedback::query()->create([
            'user_id' => $user->getKey(),
            'question_id' => $question->getKey(),
            'question_session_id' => $session->getKey(),
            'target' => 'question',
            'category' => 'grammar',
            'message' => 'Lỗi chính tả',
            'status' => QuestionFeedback::STATUS_PENDING,
        ]);

        app(SyncQuestionStatsAction::class)->syncForQuestionIds([
            (string) $question->getKey(),
            (string) $otherQuestion->getKey(),
        ]);
        $question->refresh();
        $this->assertSame(1, (int) ($question->stats_cache['total_attempts'] ?? 0));

        $host = User::factory()->create();
        $host->assignRole(Role::Instructor->value);
        $classroom = Classroom::query()->create([
            'title' => 'Lớp giữ membership',
            'host_user_id' => $host->getKey(),
            'purpose' => ClassroomPurpose::FeedbackReview,
            'visibility' => ClassroomVisibility::Unlisted,
            'join_code' => 'KEEP01',
            'status' => 'active',
        ]);
        ClassroomMember::query()->create([
            'classroom_id' => $classroom->getKey(),
            'user_id' => $user->getKey(),
            'role_in_class' => MemberRole::Member,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);

        $planModel = Plan::query()->create([
            'slug' => 'premium-reset',
            'name' => 'Premium',
            'price_cents' => 199_000,
            'currency' => 'VND',
            'entitlements' => [],
            'features' => [],
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $subscription = Subscription::query()->create([
            'user_id' => $user->getKey(),
            'plan_id' => $planModel->getKey(),
            'status' => 'active',
            'source' => 'test',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        DashboardCache::forget((int) $user->getKey());
        Cache::put(DashboardCache::key((int) $user->getKey(), '7d'), ['questions_answered' => 99], 60);

        $this->actingAs($user)
            ->post(route('profile.reset-progress'), [
                'current_password' => 'password',
                'confirmation' => 'RESET',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'reset-alt']))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertNotNull($user->learning_progress_reset_at);
        $this->assertSame('nlyt', $user->study_objective);

        $this->assertDatabaseMissing('question_sessions', ['user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('question_attempts', ['user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('question_status', ['user_id' => $user->getKey()]);
        $this->assertSame(0, DailyLearningStat::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(0, TopicMastery::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(0, StudyPlan::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(0, StudyPlanTask::query()->where('study_plan_id', $plan->getKey())->count());
        $this->assertSame(0, Bookmark::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(0, BookmarkFolder::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(0, AiThread::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(0, AiUsage::query()->where('user_id', $user->getKey())->count());
        $this->assertNull(Cache::get(DashboardCache::key((int) $user->getKey(), '7d')));

        $feedback->refresh();
        $this->assertNull($feedback->question_session_id);
        $this->assertSame(QuestionFeedback::STATUS_PENDING, $feedback->status);

        $this->assertDatabaseHas('classroom_members', [
            'classroom_id' => $classroom->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('billing_subscriptions', [
            'id' => $subscription->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('question_attempts', ['user_id' => $other->getKey()]);

        $question->refresh();
        $this->assertSame(0, (int) ($question->stats_cache['total_attempts'] ?? 0));
        $otherQuestion->refresh();
        $this->assertSame(1, (int) ($otherQuestion->stats_cache['total_attempts'] ?? 0));
    }

    public function test_wrong_password_or_confirmation_is_rejected(): void
    {
        $user = $this->student();
        $this->seedCompletedSession(
            $user,
            Question::query()->create([
                'stem' => 'Câu giữ lại khi lỗi',
                'difficulty' => 'easy',
                'status' => QuestionStatus::Published,
                'is_free' => true,
            ]),
            correct: false,
        );

        $this->actingAs($user)
            ->from(route('profile.show', ['tab' => 'reset-alt']))
            ->post(route('profile.reset-progress'), [
                'current_password' => 'wrong-password',
                'confirmation' => 'RESET',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'reset-alt']))
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->from(route('profile.show', ['tab' => 'reset-alt']))
            ->post(route('profile.reset-progress'), [
                'current_password' => 'password',
                'confirmation' => 'YES',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'reset-alt']))
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('question_sessions', ['user_id' => $user->getKey()]);
    }

    public function test_learner_can_reset_repeatedly(): void
    {
        $user = $this->student();

        $this->actingAs($user)
            ->post(route('profile.reset-progress'), [
                'current_password' => 'password',
                'confirmation' => 'RESET',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'reset-alt']));

        $firstResetAt = $user->fresh()->learning_progress_reset_at;
        $this->assertNotNull($firstResetAt);

        Carbon::setTestNow(now()->addMinute());

        $this->actingAs($user)
            ->post(route('profile.reset-progress'), [
                'current_password' => 'password',
                'confirmation' => 'RESET',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'reset-alt']))
            ->assertSessionHas('status');

        $this->assertTrue($user->fresh()->learning_progress_reset_at->greaterThan($firstResetAt));
    }

    public function test_action_force_sync_path_is_idempotent(): void
    {
        $user = $this->student();
        $question = Question::query()->create([
            'stem' => 'Idempotent wipe',
            'difficulty' => 'medium',
            'status' => QuestionStatus::Published,
            'is_free' => true,
        ]);
        $this->seedCompletedSession($user, $question, correct: true);

        $action = app(ResetLearnerProgressAction::class);
        $action->handle($user, forceSync: true);

        Carbon::setTestNow(now()->addDays(2));
        $user->refresh();
        $action->handle($user, forceSync: true);

        $this->assertSame(0, QuestionSession::withTrashed()->where('user_id', $user->getKey())->count());
    }

    /** @param  array<string, mixed>  $attributes */
    private function student(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::Student->value);

        return $user;
    }

    private function seedCompletedSession(User $user, Question $question, bool $correct): QuestionSession
    {
        $session = QuestionSession::query()->create([
            'user_id' => $user->getKey(),
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'question_ids' => [$question->getKey()],
            'total' => 1,
            'answered_count' => 1,
            'correct_count' => $correct ? 1 : 0,
        ]);

        QuestionAttempt::query()->create([
            'session_id' => $session->getKey(),
            'user_id' => $user->getKey(),
            'question_id' => $question->getKey(),
            'selected_option_ids' => [1],
            'is_correct' => $correct,
            'used_hint' => false,
            'time_spent_seconds' => 30,
            'flagged' => false,
            'answered_at' => now(),
        ]);

        return $session;
    }
}
