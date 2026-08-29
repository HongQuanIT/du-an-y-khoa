<?php

declare(strict_types=1);

namespace Modules\Classroom\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Classroom\Actions\CreateClassroomAction;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\Classroom;
use Modules\QuestionBank\Models\Question;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;
use Tests\Support\CreatesMedicalTaxonomy;


final class ClassroomFlowTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Role::values() as $role) {
            SpatieRole::findOrCreate($role, 'web');
        }

        config(['classroom.open_hosting' => true]);
    }

    public function test_host_can_create_schedule_start_end_and_chat_locks_after_end(): void
    {
        $host = User::factory()->create();
        $host->assignRole(Role::Student->value);

        $member = User::factory()->create();
        $member->assignRole(Role::Student->value);

        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Chữa đề Tim mạch',
            'description' => 'Demo',
            'visibility' => 'public',
        ]);
        $classroom->update(['status' => ClassroomStatus::Active]);

        $this->actingAs($member)
            ->post(route('classroom.join', $classroom))
            ->assertRedirect(route('classroom.show', $classroom));

        $this->actingAs($host)
            ->post(route('classroom.sessions.store', $classroom), [
                'title' => 'Buổi 1',
            ])
            ->assertRedirect();

        $session = $classroom->sessions()->firstOrFail();

        $this->actingAs($host)
            ->post(route('classroom.sessions.start', [$classroom, $session]))
            ->assertRedirect(route('classroom.live', [$classroom, $session]));

        $this->assertSame(LiveSessionStatus::Live, $session->fresh()->status);

        $this->actingAs($member)
            ->postJson(route('classroom.live.api.messages', [$classroom, $session]), [
                'body' => 'Xin hỏi về đáp án C',
                'type' => 'question',
            ])
            ->assertCreated();

        $this->actingAs($host)
            ->getJson(route('classroom.live.api.bootstrap', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.session.status', 'live');

        $this->actingAs($host)
            ->post(route('classroom.sessions.end', [$classroom, $session]))
            ->assertRedirect();

        $this->assertSame(LiveSessionStatus::Ended, $session->fresh()->status);

        $this->actingAs($member)
            ->postJson(route('classroom.live.api.messages', [$classroom, $session]), [
                'body' => 'Muốn hỏi thêm',
                'type' => 'chat',
            ])
            ->assertStatus(422);
    }

    public function test_host_can_mute_and_unmute_chat(): void
    {
        $host = User::factory()->create();
        $host->assignRole(Role::Student->value);

        $member = User::factory()->create();
        $member->assignRole(Role::Student->value);

        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Lớp mute chat',
            'visibility' => 'public',
        ]);
        $classroom->update(['status' => ClassroomStatus::Active]);

        $this->actingAs($member)->post(route('classroom.join', $classroom));

        $this->actingAs($host)->post(route('classroom.sessions.store', $classroom), [
            'title' => 'Buổi mute',
        ]);

        $session = $classroom->sessions()->firstOrFail();
        $this->actingAs($host)->post(route('classroom.sessions.start', [$classroom, $session]));

        $this->actingAs($host)
            ->postJson(route('classroom.live.api.mute-chat', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.chat_muted', true);

        $this->assertTrue($session->fresh()->chat_muted);

        $this->actingAs($member)
            ->postJson(route('classroom.live.api.messages', [$classroom, $session]), [
                'body' => 'Tin khi bị mute',
                'type' => 'chat',
            ])
            ->assertStatus(422);

        $this->actingAs($host)
            ->postJson(route('classroom.live.api.messages', [$classroom, $session]), [
                'body' => 'Host vẫn gửi được',
                'type' => 'chat',
            ])
            ->assertCreated();

        $this->actingAs($host)
            ->postJson(route('classroom.live.api.mute-chat', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.chat_muted', false);

        $this->assertFalse($session->fresh()->chat_muted);

        $this->actingAs($member)
            ->postJson(route('classroom.live.api.messages', [$classroom, $session]), [
                'body' => 'Tin sau khi bật lại',
                'type' => 'chat',
            ])
            ->assertCreated();
    }

    public function test_raise_hand_lists_and_can_be_dismissed_by_host(): void
    {
        $host = User::factory()->create();
        $host->assignRole(Role::Student->value);

        $member = User::factory()->create();
        $member->assignRole(Role::Student->value);

        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Lớp giơ tay',
            'visibility' => 'public',
        ]);
        $classroom->update(['status' => ClassroomStatus::Active]);

        $this->actingAs($member)->post(route('classroom.join', $classroom));

        $this->actingAs($host)->post(route('classroom.sessions.store', $classroom), [
            'title' => 'Buổi giơ tay',
        ]);
        $session = $classroom->sessions()->firstOrFail();
        $this->actingAs($host)->post(route('classroom.sessions.start', [$classroom, $session]));

        $this->actingAs($member)
            ->postJson(route('classroom.live.api.raise-hand', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.raised', true)
            ->assertJsonPath('data.hands.0.user.name', $member->name);

        $handId = $session->hands()->whereNull('acknowledged_at')->value('id');
        $this->assertNotNull($handId);

        $this->actingAs($host)
            ->getJson(route('classroom.live.api.bootstrap', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.hands.0.id', $handId);

        $this->actingAs($host)
            ->postJson(route('classroom.live.api.hands.dismiss', [$classroom, $session, $handId]))
            ->assertOk()
            ->assertJsonPath('data.dismissed', true)
            ->assertJsonPath('data.hands', []);

        $this->assertNotNull($session->hands()->find($handId)?->acknowledged_at);

        $this->actingAs($member)
            ->postJson(route('classroom.live.api.raise-hand', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.raised', true);

        $this->actingAs($member)
            ->postJson(route('classroom.live.api.raise-hand', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.raised', false)
            ->assertJsonPath('data.hands', []);
    }

    public function test_member_can_send_live_reaction(): void
    {
        $host = User::factory()->create();
        $host->assignRole(Role::Student->value);
        $member = User::factory()->create();
        $member->assignRole(Role::Student->value);

        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Lớp reaction',
            'visibility' => 'public',
        ]);
        $classroom->update(['status' => ClassroomStatus::Active]);

        $this->actingAs($member)->post(route('classroom.join', $classroom));
        $this->actingAs($host)->post(route('classroom.sessions.store', $classroom), [
            'title' => 'Buổi reaction',
        ]);
        $session = $classroom->sessions()->firstOrFail();
        $this->actingAs($host)->post(route('classroom.sessions.start', [$classroom, $session]));

        $this->actingAs($member)
            ->postJson(route('classroom.live.api.react', [$classroom, $session]), [
                'type' => 'heart',
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'heart');

        $this->actingAs($member)
            ->postJson(route('classroom.live.api.react', [$classroom, $session]), [
                'type' => 'clap',
            ])
            ->assertStatus(422);
    }

    public function test_live_room_with_question_set_syncs_panel(): void
    {
        $host = User::factory()->create();
        $host->assignRole(Role::Student->value);

        $topic = $this->makeMedicalNode([
            'name' => 'Test',
            'slug' => 'test-topic',
            'node_type' => 'system',
            'sort_order' => 1,
        ]);

        $question = Question::factory()->withOptions(2)->create([
                        'stem' => 'Câu hỏi test live',
            'stem_image_path' => 'question-images/live-ecg.png',
            'is_free' => true,
        ]);
        $question->options()->orderBy('order')->get()->each(function ($option, int $index): void {
            $option->forceFill([
                'explanation' => $index === 0
                    ? 'Giải thích đáp án A trong live.'
                    : 'Giải thích đáp án B trong live.',
            ])->save();
        });
        $firstOption = $question->options()->orderBy('order')->firstOrFail();
        $secondQuestion = Question::factory()->withOptions(2)->create([
                        'stem' => 'Câu hỏi live thứ hai',
            'is_free' => true,
        ]);
        $secondQuestion->options()->update(['explanation' => 'Giải thích câu 2']);

        $classroom = app(CreateClassroomAction::class)->handle($host, [
            'title' => 'Lớp test',
            'visibility' => 'public',
        ]);
        $classroom->update(['status' => ClassroomStatus::Active]);

        $this->actingAs($host)->post(route('classroom.sessions.store', $classroom), [
            'title' => 'Buổi có đề',
            'question_ids' => [
                (string) $question->getKey(),
                (string) $secondQuestion->getKey(),
            ],
        ]);

        $session = $classroom->sessions()->firstOrFail();
        $this->assertTrue($session->hasQuestionSet());

        $this->actingAs($host)->post(route('classroom.sessions.start', [$classroom, $session]));

        $this->actingAs($host)
            ->getJson(route('classroom.live.api.bootstrap', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.question_panel.question.options.0.explanation', null)
            ->assertJsonPath('data.question_panel.question.options.1.explanation', null)
            ->assertJsonPath('data.question_deck.0.id', (string) $question->getKey())
            ->assertJsonPath('data.question_deck.0.options.0.is_correct', (bool) $firstOption->is_correct)
            ->assertJsonPath('data.question_deck.0.options.0.explanation', 'Giải thích đáp án A trong live.')
            ->assertJsonCount(0, 'data.text_marks');

        $response = $this->actingAs($host)
            ->patchJson(route('classroom.live.api.question', [$classroom, $session]), [
                'option_id' => (int) $firstOption->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath('data.question.stem', 'Câu hỏi test live')
            ->assertJsonPath('data.question.options.0.explanation', 'Giải thích đáp án A trong live.')
            ->assertJsonPath('data.question.options.1.explanation', null)
            ->assertJsonPath('data.question.options.0.is_correct', (bool) $firstOption->is_correct)
            ->assertJsonPath('data.question.options.1.is_correct', null)
            ->assertJsonPath('data.revealed_option_ids.0', (int) $firstOption->getKey());

        $this->actingAs($host)
            ->patchJson(route('classroom.live.api.question', [$classroom, $session]), [
                'index' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.question.stem', 'Câu hỏi live thứ hai')
            ->assertJsonPath('data.question.options.0.explanation', null)
            ->assertJsonPath('data.question.options.1.explanation', null);

        $this->actingAs($host)
            ->patchJson(route('classroom.live.api.question', [$classroom, $session]), [
                'index' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.question.options.0.explanation', 'Giải thích đáp án A trong live.')
            ->assertJsonPath('data.question.options.1.explanation', null);

        $imageUrl = (string) $response->json('data.question.stem_image_url');
        $this->assertStringContainsString('question-images/live-ecg.png', $imageUrl);

        $this->actingAs($host)
            ->patchJson(route('classroom.live.api.marks', [$classroom, $session]), [
                'action' => 'add',
                'question_id' => (string) $question->getKey(),
                'target' => 'stem',
                'start' => 0,
                'end' => 3,
                'color' => 'yellow',
            ])
            ->assertOk()
            ->assertJsonPath('data.marks.0.color', 'yellow')
            ->assertJsonPath('data.marks.0.question_id', (string) $question->getKey());

        $this->actingAs($host)
            ->getJson(route('classroom.live.api.bootstrap', [$classroom, $session]))
            ->assertOk()
            ->assertJsonCount(1, 'data.text_marks');

        $this->actingAs($host)
            ->patchJson(route('classroom.live.api.marks', [$classroom, $session]), [
                'action' => 'clear',
                'question_id' => (string) $question->getKey(),
            ])
            ->assertOk()
            ->assertJsonCount(0, 'data.marks');

        $this->actingAs($host)
            ->patchJson(route('classroom.live.api.stage', [$classroom, $session]), [
                'stage_teach' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.stage_teach', true);

        $this->assertTrue((bool) $session->fresh()->stage_teach);

        $this->actingAs($host)
            ->getJson(route('classroom.live.api.bootstrap', [$classroom, $session]))
            ->assertOk()
            ->assertJsonPath('data.session.stage_teach', true);

        $this->actingAs($host)
            ->patchJson(route('classroom.live.api.stage', [$classroom, $session]), [
                'stage_teach' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.stage_teach', false);
    }
}
