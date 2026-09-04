<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserActivitySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

final class ActivityHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_start_is_stored_in_redis_without_writing_session_row(): void
    {
        $redis = Mockery::mock();
        $redis->shouldReceive('eval')->once()->andReturn(1);
        Redis::shouldReceive('connection')->once()->with('default')->andReturn($redis);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Version/17.5 Mobile Safari/604.1')
            ->postJson(route('activity.heartbeat'), [
                'event' => 'start',
                'session_id' => '0198eb31-347a-7390-b2f4-7285dd815327',
                'area' => '/dashboard',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('user_activity_sessions', 0);
    }

    public function test_leave_persists_activity_session_without_duration(): void
    {
        $redis = Mockery::mock();
        $redis->shouldReceive('hgetall')->once()->andReturn([
            'started_at' => (string) now()->subMinutes(5)->timestamp,
            'heartbeat_count' => '1',
        ]);
        $redis->shouldReceive('del')->once();
        $redis->shouldReceive('zrem')->once();
        Redis::shouldReceive('connection')->once()->with('default')->andReturn($redis);

        $user = User::factory()->create();
        $sessionId = '0198eb31-347a-7390-b2f4-7285dd815327';

        $this->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0.0.0 Safari/537.36')
            ->postJson(route('activity.heartbeat'), [
                'event' => 'leave',
                'session_id' => $sessionId,
                'area' => '/qbank/session/42',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $row = UserActivitySession::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('/qbank/session/{id}', $row->area);
        $this->assertSame(0, $row->duration_seconds);
        $this->assertSame('student', $row->portal);
    }

    public function test_leave_coalesces_refresh_of_same_screen(): void
    {
        $redis = Mockery::mock();
        $redis->shouldReceive('hgetall')->twice()->andReturn([]);
        $redis->shouldReceive('del')->twice();
        $redis->shouldReceive('zrem')->twice();
        Redis::shouldReceive('connection')->twice()->with('default')->andReturn($redis);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('activity.heartbeat'), [
                'event' => 'leave',
                'session_id' => '0198eb31-347a-7390-b2f4-7285dd815327',
                'area' => '/admin/users/1',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('activity.heartbeat'), [
                'event' => 'leave',
                'session_id' => '0198eb31-347a-7390-b2f4-7285dd815399',
                'area' => '/admin/users/1',
            ])
            ->assertOk();

        $this->assertSame(1, UserActivitySession::query()->where('user_id', $user->id)->count());
        $this->assertSame('/admin/users/{id}', UserActivitySession::query()->where('user_id', $user->id)->value('area'));
    }

    public function test_guest_cannot_send_activity_heartbeat(): void
    {
        $this->postJson(route('activity.heartbeat'), [
            'event' => 'start',
            'session_id' => '0198eb31-347a-7390-b2f4-7285dd815327',
            'area' => '/dashboard',
        ])->assertUnauthorized();
    }

    public function test_legacy_payload_without_event_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('activity.heartbeat'), [
                'session_id' => '0198eb31-347a-7390-b2f4-7285dd815327',
                'area' => '/dashboard',
            ])
            ->assertStatus(422);
    }
}
