<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus as PublicationStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionStatus;
use Modules\QuestionBank\Services\AdaptiveQuestionSelector;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

/**
 * Adaptive picker: Weakness + Memory × mode + cooldown (see docs/adaptive-session-algorithm.md).
 */
final class AdaptiveSessionSelectionTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private Lesson $lesson;

    private Blueprint $blueprint;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::findOrCreate(Role::Student->value, 'web');
        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);
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

    public function test_weak_focus_prefers_high_weakness_over_long_unseen_strong(): void
    {
        $weakRecent = $this->seedQuestion('Weak recent');
        $strongStale = $this->seedQuestion('Strong stale');

        // Weak + vừa gặp: W≈0.75, M thấp
        QuestionStatus::query()->create([
            'user_id' => $this->user->id,
            'question_id' => $weakRecent->getKey(),
            'status' => UserQuestionStatus::Incorrect,
            'attempts_count' => 10,
            'correct_count' => 2,
            'wrong_count' => 8,
            'omitted_count' => 0,
            'last_attempt_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
            'last_served_at' => now()->subDays(10),
        ]);

        // Mạnh + lâu chưa gặp: W thấp, M cao
        QuestionStatus::query()->create([
            'user_id' => $this->user->id,
            'question_id' => $strongStale->getKey(),
            'status' => UserQuestionStatus::Correct,
            'attempts_count' => 10,
            'correct_count' => 9,
            'wrong_count' => 1,
            'omitted_count' => 0,
            'last_attempt_at' => now()->subDays(40),
            'last_seen_at' => now()->subDays(40),
            'last_served_at' => now()->subDays(40),
        ]);

        $selector = app(AdaptiveQuestionSelector::class);
        $data = new CreateSessionData(
            mode: SessionMode::Study,
            source: SessionSource::WeakTopics,
            count: 1,
            blueprintId: $this->blueprint->id,
            adaptiveFocus: 'weak_focus',
        );

        $weakWins = 0;
        for ($i = 0; $i < 80; $i++) {
            $picked = $selector->pick((int) $this->user->id, 1, true, $data);
            if (($picked[0] ?? null) === (string) $weakRecent->getKey()) {
                $weakWins++;
            }
        }

        // weak_focus: câu yếu phải thắng rõ trong multi-trial (không cần 100%).
        $this->assertGreaterThanOrEqual(48, $weakWins, "weak_focus should prefer weak question (wins={$weakWins}/80)");
    }

    public function test_retention_prefers_long_unseen_strong_over_weak_recent(): void
    {
        $weakRecent = $this->seedQuestion('Weak recent retention');
        $strongStale = $this->seedQuestion('Strong stale retention');

        QuestionStatus::query()->create([
            'user_id' => $this->user->id,
            'question_id' => $weakRecent->getKey(),
            'status' => UserQuestionStatus::Incorrect,
            'attempts_count' => 10,
            'correct_count' => 1,
            'wrong_count' => 9,
            'omitted_count' => 0,
            'last_attempt_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
            'last_served_at' => now()->subDays(10),
        ]);

        QuestionStatus::query()->create([
            'user_id' => $this->user->id,
            'question_id' => $strongStale->getKey(),
            'status' => UserQuestionStatus::Correct,
            'attempts_count' => 10,
            'correct_count' => 8,
            'wrong_count' => 2,
            'omitted_count' => 0,
            'last_attempt_at' => now()->subDays(40),
            'last_seen_at' => now()->subDays(40),
            'last_served_at' => now()->subDays(40),
        ]);

        $selector = app(AdaptiveQuestionSelector::class);
        $data = new CreateSessionData(
            mode: SessionMode::Study,
            source: SessionSource::WeakTopics,
            count: 1,
            blueprintId: $this->blueprint->id,
            adaptiveFocus: 'retention',
        );

        $staleWins = 0;
        for ($i = 0; $i < 40; $i++) {
            $picked = $selector->pick((int) $this->user->id, 1, true, $data);
            if (($picked[0] ?? null) === (string) $strongStale->getKey()) {
                $staleWins++;
            }
        }

        $this->assertGreaterThanOrEqual(24, $staleWins, "retention should prefer stale strong (wins={$staleWins}/40)");
    }

    public function test_adaptive_session_store_logs_and_returns_requested_count(): void
    {
        Log::spy();

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

        $session = \Modules\QuestionBank\Models\QuestionSession::query()->latest('id')->firstOrFail();
        $this->assertSame(2, $session->total);
        $this->assertSame('balanced', $session->filters['adaptive_focus']);
        $this->assertCount(2, $session->question_ids);

        Log::shouldHaveReceived('debug')->withArgs(function (string $message): bool {
            return str_starts_with($message, '[adaptive]');
        })->atLeast()->once();
    }

    private function seedQuestion(string $stem): Question
    {
        $question = Question::factory()->create([
            'stem' => $stem,
            'status' => PublicationStatus::Published,
            'is_free' => true,
            'difficulty' => Difficulty::Medium,
        ]);
        $question->lessons()->sync([$this->lesson->id]);
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
}
