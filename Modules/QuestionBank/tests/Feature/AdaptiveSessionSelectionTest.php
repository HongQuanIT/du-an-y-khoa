<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Models\LearnerProfile;
use Modules\Auth\Models\Profession;
use Modules\QuestionBank\Actions\AnswerQuestionAction;
use Modules\QuestionBank\Actions\CompleteQuestionSessionAction;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus as PublicationStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus;
use Modules\QuestionBank\Services\AdaptiveQuestionSelector;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

/**
 * Adaptive V2: filter → group → quota (mophong khung).
 */
final class AdaptiveSessionSelectionTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private Lesson $lesson;

    private Blueprint $blueprint;

    private Profession $profession;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::findOrCreate(Role::Student->value, 'web');
        $this->seed(RolePermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);
        $this->profession = Profession::query()->create([
            'code' => 'adaptive-student',
            'name' => 'Sinh viên thích ứng',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        LearnerProfile::query()->create([
            'user_id' => $this->user->id,
            'profession_id' => $this->profession->id,
            'onboarding_completed_at' => now(),
        ]);
        $this->lesson = $this->makeLesson([
            'name' => 'Adaptive lesson',
            'slug' => 'adaptive-lesson-select',
            'sort_order' => 1,
        ]);

        $this->blueprint = Blueprint::query()->create([
            'name' => 'Adaptive BP',
            'slug' => 'adaptive-bp-select',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $section = BlueprintSection::query()->create([
            'blueprint_id' => $this->blueprint->id,
            'name' => 'S1',
            'slug' => 'adaptive-bp-s1',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $cct = CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $section->id,
            'name' => 'CCT',
            'slug' => 'adaptive-bp-cct',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $cct->lessons()->sync([$this->lesson->id]);
    }

    public function test_weak_focus_picks_from_weak_pool(): void
    {
        $weakRecent = $this->seedQuestion('Weak recent');
        $strongStale = $this->seedQuestion('Strong stale');

        QuestionStatus::query()->create([
            'user_id' => $this->user->id,
            'question_id' => $weakRecent->getKey(),
            'status' => UserQuestionStatus::Incorrect,
            'attempts_count' => 5,
            'correct_count' => 1,
            'wrong_count' => 4,
            'omitted_count' => 0,
            'recent_results' => [false, false, false, false, true],
            'wrong_streak' => 0,
            'last_attempt_at' => now()->subDays(2),
            'last_seen_at' => now()->subDays(2),
            'last_graded_at' => now()->subDays(2),
            'memory_stability_days' => 1,
            'last_served_at' => now()->subDays(10),
            'content_version' => 1,
        ]);

        QuestionStatus::query()->create([
            'user_id' => $this->user->id,
            'question_id' => $strongStale->getKey(),
            'status' => UserQuestionStatus::Correct,
            'attempts_count' => 5,
            'correct_count' => 5,
            'wrong_count' => 0,
            'omitted_count' => 0,
            'recent_results' => [true, true, true, true, true],
            'wrong_streak' => 0,
            'last_attempt_at' => now()->subDays(40),
            'last_seen_at' => now()->subDays(40),
            'last_graded_at' => now()->subDays(40),
            'memory_stability_days' => 20,
            'last_served_at' => now()->subDays(40),
            'content_version' => 1,
        ]);

        $selector = app(AdaptiveQuestionSelector::class);
        $data = new CreateSessionData(
            mode: SessionMode::Study,
            source: SessionSource::WeakTopics,
            count: 1,
            blueprintId: $this->blueprint->id,
            adaptiveFocus: 'weak_focus',
        );

        $picked = $selector->pick((int) $this->user->id, 1, true, $data);
        $this->assertSame([(string) $weakRecent->getKey()], $picked);
    }

    public function test_retention_picks_due_over_weak_not_due(): void
    {
        $weakRecent = $this->seedQuestion('Weak recent retention');
        $strongStale = $this->seedQuestion('Strong stale retention');

        // Yếu nhưng chưa đến hạn (t < S)
        QuestionStatus::query()->create([
            'user_id' => $this->user->id,
            'question_id' => $weakRecent->getKey(),
            'status' => UserQuestionStatus::Incorrect,
            'attempts_count' => 5,
            'correct_count' => 0,
            'wrong_count' => 5,
            'omitted_count' => 0,
            'recent_results' => [false, false, false, false, false],
            'wrong_streak' => 2,
            'last_attempt_at' => now()->subHours(2),
            'last_seen_at' => now()->subHours(2),
            'last_graded_at' => now()->subHours(2),
            'memory_stability_days' => 3,
            'last_served_at' => now()->subDays(10),
            'content_version' => 1,
        ]);

        QuestionStatus::query()->create([
            'user_id' => $this->user->id,
            'question_id' => $strongStale->getKey(),
            'status' => UserQuestionStatus::Correct,
            'attempts_count' => 5,
            'correct_count' => 5,
            'wrong_count' => 0,
            'omitted_count' => 0,
            'recent_results' => [true, true, true, true, true],
            'wrong_streak' => 0,
            'last_attempt_at' => now()->subDays(40),
            'last_seen_at' => now()->subDays(40),
            'last_graded_at' => now()->subDays(40),
            'memory_stability_days' => 20,
            'last_served_at' => now()->subDays(40),
            'content_version' => 1,
        ]);

        $picked = app(AdaptiveQuestionSelector::class)->pick(
            (int) $this->user->id,
            1,
            true,
            new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::WeakTopics,
                count: 1,
                blueprintId: $this->blueprint->id,
                adaptiveFocus: 'retention',
            ),
        );

        $this->assertSame([(string) $strongStale->getKey()], $picked);
    }

    public function test_lesson_session_does_not_set_adaptive_serve_cooldown(): void
    {
        $question = $this->seedQuestion('Practiced in lesson session');

        app(\Modules\QuestionBank\Actions\CreateQuestionSessionAction::class)->handle(
            $this->user,
            new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::Custom,
                count: 1,
                blueprintId: $this->blueprint->id,
                lessonIds: [$this->lesson->id],
            ),
        );

        $status = QuestionStatus::query()
            ->where('user_id', $this->user->id)
            ->where('question_id', $question->getKey())
            ->first();

        $this->assertTrue($status === null || $status->last_served_at === null);

        $picked = app(AdaptiveQuestionSelector::class)->pick(
            (int) $this->user->id,
            1,
            true,
            new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::WeakTopics,
                count: 1,
                blueprintId: $this->blueprint->id,
                adaptiveFocus: 'balanced',
            ),
        );

        $this->assertSame([(string) $question->getKey()], $picked);
    }

    public function test_cooldown_excludes_recently_served_questions(): void
    {
        $fresh = $this->seedQuestion('Served one hour ago');
        $ok = $this->seedQuestion('Served long ago');

        $this->recordServe($fresh, now()->subHour());
        $this->recordServe($ok, now()->subDays(10));

        $excluded = null;
        $resting = null;
        Log::listen(function (MessageLogged $event) use (&$excluded, &$resting): void {
            if ($event->message === '[adaptive] filter') {
                $excluded = $event->context['excluded'] ?? null;
                $resting = $event->context['resting'] ?? null;
            }
        });

        $picked = app(AdaptiveQuestionSelector::class)->pick(
            (int) $this->user->id,
            1,
            true,
            new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::WeakTopics,
                count: 1,
                blueprintId: $this->blueprint->id,
                adaptiveFocus: 'balanced',
            ),
        );

        $this->assertSame([(string) $ok->getKey()], $picked);
        $this->assertIsArray($excluded);
        $this->assertGreaterThanOrEqual(1, (int) ($excluded['cooldown'] ?? 0));
        $this->assertIsArray($resting);
        $restIds = array_column($resting, 'question_id');
        $this->assertContains((string) $fresh->getKey(), $restIds);
        $cooldownRow = collect($resting)->firstWhere('question_id', (string) $fresh->getKey());
        $this->assertSame('cooldown', $cooldownRow['reason'] ?? null);
        $this->assertNotEmpty($cooldownRow['due_at'] ?? null);
        $this->assertNotEmpty($cooldownRow['rest_until'] ?? null);
    }

    public function test_due_pool_orders_lower_retention_first_for_retention_mode(): void
    {
        $fragile = $this->seedQuestion('Fragile');
        $durable = $this->seedQuestion('Durable');
        $gradedAt = now()->subDays(10);

        foreach ([[$fragile, 0.5], [$durable, 32.0]] as [$question, $stability]) {
            QuestionStatus::query()->create([
                'user_id' => $this->user->id,
                'question_id' => $question->getKey(),
                'status' => UserQuestionStatus::Correct,
                'attempts_count' => 4,
                'correct_count' => 3,
                'wrong_count' => 1,
                'omitted_count' => 0,
                'recent_results' => [true, true, true, false],
                'wrong_streak' => 0,
                'last_attempt_at' => $gradedAt,
                'last_seen_at' => $gradedAt,
                'last_graded_at' => $gradedAt,
                'memory_stability_days' => $stability,
                'last_served_at' => now()->subDays(30),
                'content_version' => 1,
            ]);
        }

        $picked = app(AdaptiveQuestionSelector::class)->pick(
            (int) $this->user->id,
            1,
            true,
            new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::WeakTopics,
                count: 1,
                blueprintId: $this->blueprint->id,
                adaptiveFocus: 'retention',
            ),
        );

        $this->assertSame([(string) $fragile->getKey()], $picked);
    }

    public function test_correct_doubles_stability_and_omit_does_not_move_the_clock(): void
    {
        $graded = $this->seedQuestion('Graded once');
        $skipped = $this->seedQuestion('Skipped once');

        $session = QuestionSession::factory()->create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Active,
            'question_ids' => [(string) $graded->getKey(), (string) $skipped->getKey()],
            'total' => 2,
            'answered_count' => 0,
            'correct_count' => 0,
        ]);
        app(QuestionSessionSnapshots::class)->capture($session);

        $correctId = (int) QuestionOption::query()
            ->where('question_id', $graded->getKey())
            ->where('is_correct', true)
            ->value('id');

        app(AnswerQuestionAction::class)->handle($session, $graded, [$correctId], timeSpentSeconds: 30, autoComplete: false);

        $status = QuestionStatus::query()->where('question_id', $graded->getKey())->firstOrFail();
        // Lần đầu đúng → bậc 2 (S = 3 ngày)
        $this->assertEquals(3.0, (float) $status->memory_stability_days);
        $this->assertNotNull($status->last_graded_at);
        $this->assertSame([true], $status->recent_results);

        app(CompleteQuestionSessionAction::class)->handle($session->fresh());

        $status->refresh();
        $this->assertEquals(3.0, (float) $status->memory_stability_days);

        $omitted = QuestionStatus::query()->where('question_id', $skipped->getKey())->firstOrFail();
        $this->assertSame(UserQuestionStatus::Omitted, $omitted->status);
        $this->assertNull($omitted->memory_stability_days);
        $this->assertNull($omitted->last_graded_at);
        $this->assertNotNull($omitted->last_seen_at);

        // Đúng sớm (t < S): giữ bậc 2 / S = 3
        $again = QuestionSession::factory()->create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Active,
            'question_ids' => [(string) $graded->getKey()],
            'total' => 1,
            'answered_count' => 0,
            'correct_count' => 0,
        ]);
        app(QuestionSessionSnapshots::class)->capture($again);

        app(AnswerQuestionAction::class)->handle($again, $graded, [$correctId], timeSpentSeconds: 30, autoComplete: false);

        $this->assertEquals(3.0, (float) $status->fresh()->memory_stability_days);

        // Đúng đúng hạn → lên bậc 3 / S = 7
        $status->forceFill(['last_graded_at' => now()->subDays(4)])->save();
        $dueSession = QuestionSession::factory()->create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Active,
            'question_ids' => [(string) $graded->getKey()],
            'total' => 1,
            'answered_count' => 0,
            'correct_count' => 0,
        ]);
        app(QuestionSessionSnapshots::class)->capture($dueSession);
        app(AnswerQuestionAction::class)->handle($dueSession, $graded, [$correctId], timeSpentSeconds: 30, autoComplete: false);
        $this->assertEquals(7.0, (float) $status->fresh()->memory_stability_days);
    }

    public function test_new_question_reason_includes_lesson_name(): void
    {
        $oldLesson = $this->lesson;
        $newLesson = $this->makeLesson([
            'name' => 'Bài học mới tim mạch',
            'slug' => 'bai-hoc-moi-tim-mach',
        ]);
        CoreClinicalTopic::query()->firstOrFail()->lessons()->syncWithoutDetaching([$newLesson->id]);

        // Một câu đã chấm thuộc bài cũ → bài đang học; câu unseen thuộc cùng bài + bài mới.
        $gradedOld = $this->seedQuestion('Graded in old lesson');
        $newInOld = $this->seedQuestion('New in old lesson');
        $newInNew = Question::factory()->create([
            'stem' => 'New in brand-new lesson',
            'status' => PublicationStatus::Published,
            'is_free' => true,
            'difficulty' => Difficulty::Medium,
            'published_version' => 1,
        ]);
        $newInNew->lessons()->sync([$newLesson->id]);
        $newInNew->blueprints()->sync([$this->blueprint->id]);
        $newInNew->professions()->sync([$this->profession->id]);
        QuestionOption::factory()->create([
            'question_id' => $newInNew->getKey(),
            'is_correct' => true,
            'order' => 1,
        ]);

        QuestionStatus::query()->create([
            'user_id' => $this->user->id,
            'question_id' => $gradedOld->getKey(),
            'status' => UserQuestionStatus::Correct,
            'attempts_count' => 2,
            'correct_count' => 2,
            'wrong_count' => 0,
            'omitted_count' => 0,
            'recent_results' => [true, true],
            'wrong_streak' => 0,
            'last_attempt_at' => now()->subDays(10),
            'last_seen_at' => now()->subDays(10),
            'last_graded_at' => now()->subDays(10),
            'memory_stability_days' => 30,
            'last_served_at' => now()->subDays(30),
            'content_version' => 1,
        ]);

        $reasons = [];
        Log::listen(function (MessageLogged $event) use (&$reasons): void {
            if ($event->message === '[adaptive] result') {
                foreach ((array) ($event->context['items'] ?? []) as $item) {
                    if (($item['bucket'] ?? null) === 'moi') {
                        $reasons[(string) $item['question_id']] = (string) ($item['reason'] ?? '');
                    }
                }
            }
        });

        app(AdaptiveQuestionSelector::class)->pick(
            (int) $this->user->id,
            2,
            true,
            new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::WeakTopics,
                count: 2,
                blueprintId: $this->blueprint->id,
                adaptiveFocus: 'balanced',
            ),
        );

        $this->assertArrayHasKey((string) $newInOld->getKey(), $reasons);
        $this->assertStringContainsString('Câu mới — tiếp tục bài đang học:', $reasons[(string) $newInOld->getKey()]);
        $this->assertStringContainsString($oldLesson->name, $reasons[(string) $newInOld->getKey()]);

        // Chỉ còn unseen ở bài mới → nhãn "bài học mới".
        $reasons = [];
        $newInOld->delete();
        app(AdaptiveQuestionSelector::class)->pick(
            (int) $this->user->id,
            1,
            true,
            new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::WeakTopics,
                count: 1,
                blueprintId: $this->blueprint->id,
                adaptiveFocus: 'balanced',
            ),
        );
        $this->assertArrayHasKey((string) $newInNew->getKey(), $reasons);
        $this->assertStringContainsString('Câu mới — bài học mới:', $reasons[(string) $newInNew->getKey()]);
        $this->assertStringContainsString($newLesson->name, $reasons[(string) $newInNew->getKey()]);
    }

    public function test_completing_adaptive_session_logs_graded_table(): void
    {
        $question = $this->seedQuestion('Grade log question');
        $graded = null;
        Log::listen(function (MessageLogged $event) use (&$graded): void {
            if ($event->message === '[adaptive] graded') {
                $graded = $event->context;
            }
        });

        $this->actingAs($this->user)
            ->post(route('qbank.store'), [
                'mode' => SessionMode::Study->value,
                'source' => 'weak_topics',
                'adaptive_focus' => 'balanced',
                'count' => 1,
                'blueprint_id' => $this->blueprint->id,
            ])
            ->assertRedirect();

        $session = QuestionSession::query()->latest('id')->firstOrFail();
        $this->assertNotEmpty($session->filters['adaptive_trace_id'] ?? null);
        $pickedId = (string) ($session->question_ids[0] ?? '');
        $this->assertSame((string) $question->getKey(), $pickedId);
        $picked = Question::query()->findOrFail($pickedId);

        $correctId = (int) QuestionOption::query()
            ->where('question_id', $picked->getKey())
            ->where('is_correct', true)
            ->value('id');

        app(AnswerQuestionAction::class)->handle($session, $picked, [$correctId], timeSpentSeconds: 30, autoComplete: true);

        $this->assertIsArray($graded);
        $this->assertSame(1, (int) ($graded['total'] ?? 0));
        $this->assertSame(1, (int) ($graded['correct_count'] ?? 0));
        $item = $graded['items'][0] ?? [];
        $this->assertSame('correct', $item['result'] ?? null);
        $this->assertSame(3.0, (float) ($item['s_after'] ?? 0));
        $this->assertStringContainsString('Lần đầu đúng', (string) ($item['note'] ?? ''));
        $this->assertSame($session->filters['adaptive_trace_id'], $graded['trace_id'] ?? null);
    }

    public function test_adaptive_session_store_logs_v2_steps(): void
    {
        $steps = [];
        Log::listen(function (MessageLogged $event) use (&$steps): void {
            if (str_starts_with($event->message, '[adaptive] ')) {
                $steps[] = substr($event->message, strlen('[adaptive] '));
            }
        });

        $this->seedQuestion('A');
        $this->seedQuestion('B');
        $this->seedQuestion('C');

        $this->actingAs($this->user)
            ->post(route('qbank.store'), [
                'mode' => SessionMode::Study->value,
                'source' => 'weak_topics',
                'adaptive_focus' => 'balanced',
                'count' => 2,
                'blueprint_id' => $this->blueprint->id,
            ])
            ->assertRedirect();

        $session = QuestionSession::query()->latest('id')->firstOrFail();
        $this->assertSame(2, $session->total);
        $this->assertSame('balanced', $session->filters['adaptive_focus']);
        $this->assertCount(2, $session->question_ids);

        $this->assertContains('start', $steps);
        $this->assertContains('filter', $steps);
        $this->assertContains('group', $steps);
        $this->assertContains('quota', $steps);
        $this->assertContains('result', $steps);
        $this->assertContains('served', $steps);
    }

    private function seedQuestion(string $stem): Question
    {
        $question = Question::factory()->create([
            'stem' => $stem,
            'status' => PublicationStatus::Published,
            'is_free' => true,
            'difficulty' => Difficulty::Medium,
            'published_version' => 1,
        ]);
        $question->lessons()->sync([$this->lesson->id]);
        $question->blueprints()->sync([$this->blueprint->id]);
        $question->professions()->sync([$this->profession->id]);
        QuestionOption::factory()->create([
            'question_id' => $question->getKey(),
            'is_correct' => true,
            'order' => 1,
        ]);
        QuestionOption::factory()->create([
            'question_id' => $question->getKey(),
            'is_correct' => false,
            'order' => 2,
        ]);

        return $question;
    }

    private function recordServe(Question $question, \DateTimeInterface $servedAt): void
    {
        QuestionStatus::query()->create([
            'user_id' => $this->user->id,
            'question_id' => $question->getKey(),
            'status' => UserQuestionStatus::Incorrect,
            'attempts_count' => 4,
            'correct_count' => 1,
            'wrong_count' => 3,
            'omitted_count' => 0,
            'recent_results' => [false, false, false, true],
            'wrong_streak' => 0,
            'last_attempt_at' => $servedAt,
            'last_seen_at' => $servedAt,
            'last_graded_at' => now()->subDays(5),
            'memory_stability_days' => 1,
            'last_served_at' => $servedAt,
            'content_version' => 1,
        ]);
    }
}
