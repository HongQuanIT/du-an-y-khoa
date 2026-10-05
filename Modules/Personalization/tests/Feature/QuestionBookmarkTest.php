<?php

declare(strict_types=1);

namespace Modules\Personalization\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Personalization\Models\Bookmark;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Services\SessionQuestionSelector;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;
use Modules\StudyPlan\Services\PlanQuestionSelector;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

final class QuestionBookmarkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::findOrCreate(Role::Student->value, 'web');
        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);
    }

    public function test_student_can_save_and_remove_a_question_bookmark(): void
    {
        $question = Question::factory()->free()->create([]);

        $this->actingAs($this->user)
            ->postJson(route('bookmarks.questions.set', $question), ['bookmarked' => true])
            ->assertOk()
            ->assertJsonPath('data.bookmarked', true);

        $this->assertTrue(Bookmark::hasQuestion((int) $this->user->id, (string) $question->id));

        // Repeating the desired state is idempotent.
        $this->actingAs($this->user)
            ->postJson(route('bookmarks.questions.set', $question), ['bookmarked' => true])
            ->assertOk();
        $this->assertDatabaseCount('bookmarks', 1);

        $this->actingAs($this->user)
            ->postJson(route('bookmarks.questions.set', $question), ['bookmarked' => false])
            ->assertOk()
            ->assertJsonPath('data.bookmarked', false);

        $this->assertDatabaseCount('bookmarks', 0);
    }

    public function test_student_can_manage_bookmark_folders_and_toggle_items(): void
    {
        $question = Question::factory()->free()->create([]);

        // Fetch folders -> auto creates default folder "câu hỏi lưu"
        $this->actingAs($this->user)
            ->getJson(route('bookmarks.folders.index').'?question_id='.$question->id)
            ->assertOk()
            ->assertJsonPath('data.bookmarked', false)
            ->assertJsonPath('data.folders.0.name', 'câu hỏi lưu');

        // Create custom folder "HY"
        $response = $this->actingAs($this->user)
            ->postJson(route('bookmarks.folders.store'), [
                'name' => 'HY',
                'question_id' => (string) $question->id,
            ])
            ->assertOk();

        $folderId = $response->json('data.folders.1.id');
        $this->assertTrue($response->json('data.bookmarked'));
        $this->assertTrue(Bookmark::hasQuestion((int) $this->user->id, (string) $question->id));

        // Toggle question out of "HY" folder
        $toggleResponse = $this->actingAs($this->user)
            ->postJson(route('bookmarks.folders.toggle', ['folder' => $folderId]), [
                'question_id' => (string) $question->id,
                'in_folder' => false,
            ])
            ->assertOk();

        $this->assertFalse($toggleResponse->json('data.bookmarked'));
        $this->assertFalse(Bookmark::hasQuestion((int) $this->user->id, (string) $question->id));
    }

    public function test_qbank_saved_only_uses_bookmarks(): void
    {
        $saved = Question::factory()->free()->create([]);
        Question::factory()->free()->create([]);
        $this->bookmark($saved);

        $ids = app(SessionQuestionSelector::class)->forSession(
            $this->user,
            new CreateSessionData(count: 10, savedOnly: true),
        );

        $this->assertSame([(string) $saved->id], $ids);
    }

    public function test_study_plan_saved_only_uses_bookmarks(): void
    {
        $saved = Question::factory()->create([]);
        Question::factory()->create([]);
        $this->bookmark($saved);

        $plan = StudyPlan::factory()->create([
            'user_id' => $this->user->id,
            'topic_scope' => [
                'topic_ids' => [],
                'saved_only' => true,
                'question_statuses' => [],
                'question_status_mode' => 'latest',
                'difficulties' => [],
            ],
        ]);
        $task = StudyPlanTask::factory()->create([
            'study_plan_id' => $plan->id,
            'target' => 10,
        ]);

        $ids = app(PlanQuestionSelector::class)->forTask($task, 10);

        $this->assertSame([(string) $saved->id], $ids);
    }

    private function bookmark(Question $question): void
    {
        Bookmark::query()->create([
            'user_id' => $this->user->id,
            'bookmarkable_type' => Bookmark::TYPE_QUESTION,
            'bookmarkable_id' => (string) $question->id,
        ]);
    }
}
