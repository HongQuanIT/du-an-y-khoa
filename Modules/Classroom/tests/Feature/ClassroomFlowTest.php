<?php

declare(strict_types=1);

namespace Modules\Classroom\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\Classroom;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Topic;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

final class ClassroomFlowTest extends TestCase
{
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

        $this->actingAs($host)
            ->post(route('classroom.store'), [
                'title' => 'Chữa đề Tim mạch',
                'description' => 'Demo',
                'visibility' => 'public',
            ])
            ->assertRedirect();

        $classroom = Classroom::query()->firstOrFail();

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

        $this->actingAs($host)->post(route('classroom.store'), [
            'title' => 'Lớp mute chat',
            'visibility' => 'public',
        ]);

        $classroom = Classroom::query()->firstOrFail();

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

        $this->actingAs($host)->post(route('classroom.store'), [
            'title' => 'Lớp giơ tay',
            'visibility' => 'public',
        ]);

        $classroom = Classroom::query()->firstOrFail();
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

        $this->actingAs($host)->post(route('classroom.store'), [
            'title' => 'Lớp reaction',
            'visibility' => 'public',
        ]);
        $classroom = Classroom::query()->firstOrFail();
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

        $topic = Topic::create([
            'name' => 'Test',
            'slug' => 'test-topic',
            'type' => 'system',
            'order' => 1,
        ]);

        $question = Question::factory()->create([
            'topic_id' => $topic->id,
            'stem' => 'Câu hỏi test live',
            'is_free' => true,
        ]);

        $this->actingAs($host)->post(route('classroom.store'), [
            'title' => 'Lớp test',
            'visibility' => 'public',
        ]);

        $classroom = Classroom::query()->firstOrFail();

        $this->actingAs($host)->post(route('classroom.sessions.store', $classroom), [
            'title' => 'Buổi có đề',
            'question_ids' => [(string) $question->getKey()],
        ]);

        $session = $classroom->sessions()->firstOrFail();
        $this->assertTrue($session->hasQuestionSet());

        $this->actingAs($host)->post(route('classroom.sessions.start', [$classroom, $session]));

        $this->actingAs($host)
            ->patchJson(route('classroom.live.api.question', [$classroom, $session]), [
                'show_answer' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.question.stem', 'Câu hỏi test live');
    }
}
