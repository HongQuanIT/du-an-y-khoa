<?php

declare(strict_types=1);

namespace Modules\Personalization\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Personalization\Models\Note;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Subject;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class NotesCrudTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private Lesson $lesson;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);

        $this->subject = $this->makeSubject([
            'name' => 'Nội khoa',
            'slug' => 'noi-khoa',
        ]);
        $this->lesson = $this->makeLesson([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach',
        ]);
        $this->lesson->subjects()->attach($this->subject->id, ['sort_order' => 0]);
    }

    public function test_student_can_upsert_question_note_and_it_persists_across_lookups(): void
    {
        $question = Question::factory()->free()->create([]);
        $question->lessons()->attach($this->lesson->id);

        $this->actingAs($this->user)
            ->postJson(route('notes.store'), [
                'notable_type' => Note::TYPE_QUESTION,
                'notable_id' => (string) $question->id,
                'body_html' => '<p onclick="x"><strong>Nhớ ADH</strong></p><script>alert(1)</script>',
                'color' => '#3B82F6',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Nhớ ADH')
            ->assertJsonPath('data.body_html', '<p><strong>Nhớ ADH</strong></p>')
            ->assertJsonPath('data.notable_type', 'question');

        $this->assertDatabaseCount('notes', 1);

        // Second upsert updates the same row.
        $this->actingAs($this->user)
            ->postJson(route('notes.store'), [
                'notable_type' => Note::TYPE_QUESTION,
                'notable_id' => (string) $question->id,
                'body_html' => '<p>Cập nhật</p>',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('notes', 1);
        $this->assertSame('Cập nhật', Note::forUserQuestion((int) $this->user->id, (string) $question->id)->value('body'));
    }

    public function test_student_can_create_free_notes_and_delete(): void
    {
        $create = $this->actingAs($this->user)
            ->postJson(route('notes.store'), [
                'body_html' => '<p>Sổ tay tự do</p>',
            ])
            ->assertCreated();

        $noteId = (int) $create->json('data.id');

        $this->actingAs($this->user)
            ->get(route('notes.index', ['view' => 'notebook']))
            ->assertOk()
            ->assertSee('Sổ tay tự do', false);

        $this->actingAs($this->user)
            ->deleteJson(route('notes.destroy', $noteId))
            ->assertNoContent();

        $this->assertSoftDeleted('notes', ['id' => $noteId]);
    }

    public function test_notes_can_be_grouped_by_subject_or_lesson(): void
    {
        $otherSubject = $this->makeSubject(['name' => 'Ngoại khoa', 'slug' => 'ngoai-khoa', 'sort_order' => 2]);
        $this->subject->forceFill(['sort_order' => 1])->save();

        $otherLesson = $this->makeLesson(['name' => 'Chấn thương', 'slug' => 'chan-thuong', 'sort_order' => 1]);
        $otherLesson->subjects()->attach($otherSubject->id, ['sort_order' => 0]);

        $q1 = Question::factory()->free()->create([]);
        $q1->lessons()->attach($this->lesson->id);
        $q2 = Question::factory()->free()->create([]);
        $q2->lessons()->attach($otherLesson->id);

        Note::query()->create([
            'user_id' => $this->user->id,
            'notable_type' => Note::TYPE_QUESTION,
            'notable_id' => (string) $q1->id,
            'body' => 'Note tim mạch',
            'body_html' => '<p>Note tim mạch</p>',
        ]);
        Note::query()->create([
            'user_id' => $this->user->id,
            'notable_type' => Note::TYPE_QUESTION,
            'notable_id' => (string) $q2->id,
            'body' => 'Note chấn thương',
            'body_html' => '<p>Note chấn thương</p>',
        ]);

        $bySubject = $this->actingAs($this->user)
            ->getJson(route('notes.index', ['view' => 'subject']))
            ->assertOk()
            ->assertJsonPath('data.mode', 'grouped')
            ->json('data.groups');

        $this->assertSame('Nội khoa', $bySubject[0]['title']);
        $this->assertSame('Note tim mạch', $bySubject[0]['notes'][0]['body']);
        $this->assertSame('Ngoại khoa', $bySubject[1]['title']);

        $byLesson = $this->actingAs($this->user)
            ->getJson(route('notes.index', ['view' => 'lesson']))
            ->assertOk()
            ->json('data.groups');

        $titles = array_column($byLesson, 'title');
        $this->assertContains('Tim mạch', $titles);
        $this->assertContains('Chấn thương', $titles);
    }

    public function test_search_matches_body_subject_and_lesson_names(): void
    {
        $otherSubject = $this->makeSubject(['name' => 'Ngoại khoa', 'slug' => 'ngoai-khoa-search']);
        $otherLesson = $this->makeLesson(['name' => 'Chấn thương', 'slug' => 'chan-thuong-search']);
        $otherLesson->subjects()->attach($otherSubject->id, ['sort_order' => 0]);

        $q1 = Question::factory()->free()->create([]);
        $q1->lessons()->attach($this->lesson->id);
        $q2 = Question::factory()->free()->create([]);
        $q2->lessons()->attach($otherLesson->id);

        Note::query()->create([
            'user_id' => $this->user->id,
            'notable_type' => Note::TYPE_QUESTION,
            'notable_id' => (string) $q1->id,
            'body' => 'Adenosine cắt vòng',
            'body_html' => '<p>Adenosine cắt vòng</p>',
        ]);
        Note::query()->create([
            'user_id' => $this->user->id,
            'notable_type' => Note::TYPE_QUESTION,
            'notable_id' => (string) $q2->id,
            'body' => 'Gãy xương đòn',
            'body_html' => '<p>Gãy xương đòn</p>',
        ]);

        $byBody = $this->actingAs($this->user)
            ->getJson(route('notes.index', ['view' => 'subject', 'q' => 'Adenosine']))
            ->assertOk()
            ->json('data');
        $this->assertSame(1, $byBody['total']);
        $this->assertSame('Adenosine cắt vòng', $byBody['groups'][0]['notes'][0]['body']);

        $bySubject = $this->actingAs($this->user)
            ->getJson(route('notes.index', ['view' => 'subject', 'q' => 'Ngoại']))
            ->assertOk()
            ->json('data');
        $this->assertSame(1, $bySubject['total']);
        $this->assertSame('Gãy xương đòn', $bySubject['groups'][0]['notes'][0]['body']);

        $byLesson = $this->actingAs($this->user)
            ->getJson(route('notes.index', ['view' => 'lesson', 'q' => 'Tim mạch']))
            ->assertOk()
            ->json('data');
        $this->assertSame(1, $byLesson['total']);
        $this->assertSame('Adenosine cắt vòng', $byLesson['groups'][0]['notes'][0]['body']);
    }

    public function test_notebook_view_lists_only_free_notes(): void
    {
        $question = Question::factory()->free()->create([]);
        Note::query()->create([
            'user_id' => $this->user->id,
            'notable_type' => Note::TYPE_QUESTION,
            'notable_id' => (string) $question->id,
            'body' => 'Note câu hỏi',
            'body_html' => '<p>Note câu hỏi</p>',
        ]);
        Note::query()->create([
            'user_id' => $this->user->id,
            'notable_type' => null,
            'notable_id' => null,
            'body' => 'Sổ tay cá nhân',
            'body_html' => '<p>Sổ tay cá nhân</p>',
        ]);

        $this->actingAs($this->user)
            ->getJson(route('notes.index', ['view' => 'notebook']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Sổ tay cá nhân');
    }

    public function test_index_exposes_three_view_modes(): void
    {
        $this->actingAs($this->user)
            ->get(route('notes.index'))
            ->assertOk()
            ->assertSee('Theo môn', false)
            ->assertSee('Theo bài', false)
            ->assertSee('Sổ tay', false)
            ->assertDontSee('>Loại<', false)
            ->assertDontSee('Gần đây', false);
    }

    public function test_owner_isolation_prevents_updating_another_users_note(): void
    {
        $other = User::factory()->create();
        $other->assignRole(Role::Student->value);

        $note = Note::query()->create([
            'user_id' => $other->id,
            'notable_type' => null,
            'notable_id' => null,
            'body' => 'Riêng tư',
            'body_html' => '<p>Riêng tư</p>',
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('notes.update', $note), [
                'body_html' => '<p>Hack</p>',
            ])
            ->assertForbidden();
    }

    public function test_empty_body_soft_deletes_attached_note(): void
    {
        $question = Question::factory()->free()->create([]);

        $this->actingAs($this->user)
            ->postJson(route('notes.store'), [
                'notable_type' => Note::TYPE_QUESTION,
                'notable_id' => (string) $question->id,
                'body_html' => '<p>Tạm</p>',
            ])
            ->assertCreated();

        $this->actingAs($this->user)
            ->postJson(route('notes.store'), [
                'notable_type' => Note::TYPE_QUESTION,
                'notable_id' => (string) $question->id,
                'body_html' => '<p></p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('notes', [
            'user_id' => $this->user->id,
            'notable_type' => Note::TYPE_QUESTION,
            'notable_id' => (string) $question->id,
        ]);
    }
}
