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
        $question = Question::factory()->free()->create(['topic_id' => null]);

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
        $question = Question::factory()->free()->create(['topic_id' => null]);

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
        $saved = Question::factory()->free()->create(['topic_id' => null]);
        Question::factory()->free()->create(['topic_id' => null]);
        $this->bookmark($saved);

        $ids = app(SessionQuestionSelector::class)->forSession(
            $this->user,
            new CreateSessionData(count: 10, savedOnly: true),
        );

        $this->assertSame([(string) $saved->id], $ids);
    }

    public function test_study_plan_saved_only_uses_bookmarks(): void
    {
        $saved = Question::factory()->create(['topic_id' => null]);
        Question::factory()->create(['topic_id' => null]);
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

    public function test_qbank_bookmarks_page_lists_saved_questions(): void
    {
        $saved = Question::factory()->free()->create([
            'topic_id' => null,
            'stem' => 'Bệnh nhân ho ra máu và suy thận cấp nên nghĩ đến gì?',
        ]);
        $this->bookmark($saved);

        $this->actingAs($this->user)
            ->get(route('qbank.bookmarks'))
            ->assertOk()
            ->assertSee('Câu hỏi đã lưu')
            ->assertSee('Bệnh nhân ho ra máu');
    }

    public function test_qbank_bookmarks_page_includes_question_and_answers(): void
    {
        $saved = Question::factory()->free()->create([
            'topic_id' => null,
            'stem' => 'Câu hỏi bookmark để xem đáp án?',
            'explanation' => 'Giải thích dành cho câu đã lưu.',
        ]);
        \Modules\QuestionBank\Models\QuestionOption::factory()->create([
            'question_id' => $saved->id,
            'label' => 'A',
            'content' => 'Đáp án sai của bookmark',
            'is_correct' => false,
            'order' => 0,
        ]);
        \Modules\QuestionBank\Models\QuestionOption::factory()->correct()->create([
            'question_id' => $saved->id,
            'label' => 'B',
            'content' => 'Đáp án đúng của bookmark',
            'order' => 1,
        ]);
        $this->bookmark($saved);

        $this->actingAs($this->user)
            ->get(route('qbank.bookmarks'))
            ->assertOk()
            ->assertSee('Câu hỏi bookmark để xem đáp án?')
            ->assertSee('Đáp án sai của bookmark')
            ->assertSee('Đáp án đúng của bookmark')
            ->assertSee('Đáp án đúng');
    }

    public function test_student_can_remove_bookmark_from_qbank_list(): void
    {
        $saved = Question::factory()->free()->create(['topic_id' => null]);
        $this->bookmark($saved);

        $this->actingAs($this->user)
            ->from(route('qbank.bookmarks'))
            ->delete(route('qbank.bookmarks.destroy', $saved))
            ->assertRedirect(route('qbank.bookmarks'));

        $this->assertDatabaseCount('bookmarks', 0);
    }

    public function test_student_can_start_session_from_selected_bookmarks(): void
    {
        $first = Question::factory()->free()->withOptions()->create(['topic_id' => null]);
        $second = Question::factory()->free()->withOptions()->create(['topic_id' => null]);
        Question::factory()->free()->withOptions()->create(['topic_id' => null]);
        $this->bookmark($first);
        $this->bookmark($second);

        $this->actingAs($this->user)
            ->post(route('qbank.bookmarks.session'), [
                'question_ids' => [(string) $first->id, (string) $second->id],
            ])
            ->assertRedirect();

        $session = \Modules\QuestionBank\Models\QuestionSession::query()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [(string) $first->id, (string) $second->id],
            $session->question_ids,
        );
        $this->assertTrue((bool) ($session->filters['saved_only'] ?? false));
    }

    public function test_session_from_bookmarks_ignores_questions_not_saved_by_user(): void
    {
        $owned = Question::factory()->free()->withOptions()->create(['topic_id' => null]);
        $foreign = Question::factory()->free()->withOptions()->create(['topic_id' => null]);
        $this->bookmark($owned);

        $this->actingAs($this->user)
            ->post(route('qbank.bookmarks.session'), [
                'question_ids' => [(string) $owned->id, (string) $foreign->id],
            ])
            ->assertRedirect();

        $session = \Modules\QuestionBank\Models\QuestionSession::query()->firstOrFail();
        $this->assertSame([(string) $owned->id], $session->question_ids);
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
