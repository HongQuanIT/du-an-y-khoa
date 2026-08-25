<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

final class ActivityHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_heartbeat_is_aggregated_in_redis_without_writing_audit_log(): void
    {
        $redis = Mockery::mock();
        $redis->shouldReceive('eval')->once()->andReturn(1);
        Redis::shouldReceive('connection')->once()->with('default')->andReturn($redis);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Version/17.5 Mobile Safari/604.1')
            ->postJson(route('activity.heartbeat'), [
                'session_id' => '0198eb31-347a-7390-b2f4-7285dd815327',
                'area' => '/dashboard',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('user_activity_sessions', 0);
    }

    public function test_guest_cannot_send_activity_heartbeat(): void
    {
        $this->postJson(route('activity.heartbeat'), [
            'session_id' => '0198eb31-347a-7390-b2f4-7285dd815327',
            'area' => '/dashboard',
        ])->assertUnauthorized();
    }
}
