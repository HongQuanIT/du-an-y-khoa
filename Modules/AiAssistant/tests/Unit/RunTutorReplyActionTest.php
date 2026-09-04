<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\AiAssistant\Actions\RunTutorReplyAction;
use Modules\AiAssistant\Contracts\AiTutorClient;
use Modules\AiAssistant\Contracts\TutorReply;
use Modules\AiAssistant\Enums\TutorPreset;
use Modules\AiAssistant\Models\AiMessage;
use Modules\AiAssistant\Models\AiThread;
use Modules\AiAssistant\Services\TutorPromptFactory;
use Modules\AiAssistant\Services\TutorResponseCache;
use Tests\TestCase;

final class RunTutorReplyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_truncated_to_configured_max(): void
    {
        config(['aiassistant.history_max_messages' => 4]);
        // Ensure DB setting does not override the config fallback used in this test.
        app(\App\Services\SettingService::class)->forgetCache();

        $user = User::factory()->create();
        $thread = AiThread::query()->create([
            'user_id' => $user->getKey(),
            'title' => 'Test',
            'context_source' => 'ai',
        ]);

        $base = Carbon::parse('2026-01-01 10:00:00');
        for ($i = 1; $i <= 6; $i++) {
            $msg = $thread->messages()->create([
                'user_id' => $user->getKey(),
                'role' => $i % 2 === 1 ? AiMessage::ROLE_USER : AiMessage::ROLE_ASSISTANT,
                'status' => AiMessage::STATUS_DONE,
                'content' => "m{$i}",
            ]);
            $msg->forceFill(['created_at' => $base->copy()->addSeconds($i), 'updated_at' => $base->copy()->addSeconds($i)])->save();
        }

        $assistant = $thread->messages()->create([
            'user_id' => $user->getKey(),
            'role' => AiMessage::ROLE_ASSISTANT,
            'status' => AiMessage::STATUS_PENDING,
            'content' => '',
        ]);

        $recorder = new class implements AiTutorClient
        {
            /** @var array<int, array{role: string, content: string}> */
            public array $lastMessages = [];

            public int $calls = 0;

            public function stream(string|array $system, array $messages, callable $onDelta, ?callable $shouldStop = null): TutorReply
            {
                $this->calls++;
                $this->lastMessages = $messages;
                $onDelta('ok');

                return new TutorReply('ok', [], 1, 1);
            }
        };

        $this->app->instance(AiTutorClient::class, $recorder);

        app(RunTutorReplyAction::class)->handle($thread, $assistant->fresh(), broadcast: false);

        $this->assertSame(1, $recorder->calls);
        $this->assertCount(4, $recorder->lastMessages);
        $this->assertSame(['m3', 'm4', 'm5', 'm6'], array_column($recorder->lastMessages, 'content'));
    }

    public function test_follow_up_uses_context_ref_not_full_context(): void
    {
        $user = User::factory()->create();
        $thread = AiThread::query()->create([
            'user_id' => $user->getKey(),
            'title' => 'Q',
            'context_type' => 'question',
            'context_id' => '99',
            'context_source' => 'session',
            'preset' => TutorPreset::AnalyzeWithoutSpoiler->value,
        ]);

        $thread->messages()->create([
            'user_id' => $user->getKey(),
            'role' => AiMessage::ROLE_USER,
            'status' => AiMessage::STATUS_DONE,
            'content' => 'first',
        ]);
        $thread->messages()->create([
            'user_id' => $user->getKey(),
            'role' => AiMessage::ROLE_ASSISTANT,
            'status' => AiMessage::STATUS_DONE,
            'content' => 'answer1',
        ]);
        $thread->messages()->create([
            'user_id' => $user->getKey(),
            'role' => AiMessage::ROLE_USER,
            'status' => AiMessage::STATUS_DONE,
            'content' => 'follow up please',
        ]);

        $assistant = $thread->messages()->create([
            'user_id' => $user->getKey(),
            'role' => AiMessage::ROLE_ASSISTANT,
            'status' => AiMessage::STATUS_PENDING,
            'content' => '',
        ]);

        $recorder = new class implements AiTutorClient
        {
            /** @var string|list<string> */
            public string|array $lastSystem = '';

            public function stream(string|array $system, array $messages, callable $onDelta, ?callable $shouldStop = null): TutorReply
            {
                $this->lastSystem = $system;
                $onDelta('ok');

                return new TutorReply('ok');
            }
        };

        $this->app->instance(AiTutorClient::class, $recorder);
        app(RunTutorReplyAction::class)->handle($thread, $assistant->fresh(), broadcast: false);

        $this->assertIsArray($recorder->lastSystem);
        $joined = implode("\n", $recorder->lastSystem);
        $this->assertStringContainsString('CONTEXT_REF:', $joined);
        $this->assertStringNotContainsString('CONTEXT:{', $joined);
    }

    public function test_cache_hit_skips_client(): void
    {
        $user = User::factory()->create();
        $preset = TutorPreset::AnalyzeWithoutSpoiler;
        $pack = ['question_id' => '7', 'answered' => false];
        $auto = (new TutorPromptFactory)->autoPromptContent($preset, $pack);

        $thread = AiThread::query()->create([
            'user_id' => $user->getKey(),
            'title' => 'Q',
            'context_type' => 'question',
            'context_id' => '7',
            'context_source' => 'session',
            'preset' => $preset->value,
        ]);

        app(TutorResponseCache::class)->put($thread, $pack, 'From cache', []);

        $thread->messages()->create([
            'user_id' => $user->getKey(),
            'role' => AiMessage::ROLE_USER,
            'status' => AiMessage::STATUS_DONE,
            'content' => $auto,
        ]);

        $assistant = $thread->messages()->create([
            'user_id' => $user->getKey(),
            'role' => AiMessage::ROLE_ASSISTANT,
            'status' => AiMessage::STATUS_PENDING,
            'content' => '',
        ]);

        $recorder = new class implements AiTutorClient
        {
            public int $calls = 0;

            public function stream(string|array $system, array $messages, callable $onDelta, ?callable $shouldStop = null): TutorReply
            {
                $this->calls++;

                return new TutorReply('should not run');
            }
        };

        $this->app->instance(AiTutorClient::class, $recorder);

        $result = app(RunTutorReplyAction::class)->handle($thread, $assistant->fresh(), broadcast: false);

        $this->assertSame(0, $recorder->calls);
        $this->assertSame('From cache', $result->fresh()->content);
        $this->assertSame(0, $result->fresh()->tokens_in);
    }
}
