<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserActivitySession;
use App\Support\Audit\ActivityTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

final class FlushUserActivitySessions extends Command
{
    protected $signature = 'activity:flush {--limit=1000}';

    protected $description = 'Gom các heartbeat không còn hoạt động từ Redis vào user_activity_sessions';

    public function handle(): int
    {
        $redis = Redis::connection((string) config('audit.activity.redis_connection', 'default'));
        $cutoff = now()->subSeconds((int) config('audit.activity.idle_seconds', 300))->timestamp;
        $keys = $redis->zrangebyscore(ActivityTracker::indexKey(), '-inf', (string) $cutoff, [
            'limit' => [0, max(1, (int) $this->option('limit'))],
        ]);
        $flushed = 0;

        $redisPrefix = (string) config('database.redis.options.prefix', '');

        foreach ($keys as $member) {
            $key = $redisPrefix !== '' && str_starts_with($member, $redisPrefix)
                ? substr($member, strlen($redisPrefix))
                : $member;
            $values = $redis->eval(
                <<<'LUA'
                    local lastSeen = tonumber(redis.call('HGET', KEYS[1], 'last_seen_at') or '0')
                    if lastSeen > tonumber(ARGV[1]) then return {} end
                    local values = redis.call('HGETALL', KEYS[1])
                    redis.call('DEL', KEYS[1])
                    redis.call('ZREM', KEYS[2], ARGV[2])
                    return values
                    LUA,
                2,
                $key,
                ActivityTracker::indexKey(),
                (string) $cutoff,
                $member,
            );

            if ($values === []) {
                continue;
            }

            $activity = [];
            for ($index = 0; $index < count($values); $index += 2) {
                $activity[$values[$index]] = $values[$index + 1] ?? '';
            }

            $startedAt = Carbon::createFromTimestamp((int) $activity['started_at']);
            $lastSeenAt = Carbon::createFromTimestamp((int) $activity['last_seen_at']);

            UserActivitySession::query()->updateOrCreate(
                [
                    'user_id' => (int) $activity['user_id'],
                    'session_id' => $activity['session_id'],
                    'area' => $activity['area'],
                ],
                [
                    'portal' => $activity['portal'],
                    'started_at' => $startedAt,
                    'last_seen_at' => $lastSeenAt,
                    'duration_seconds' => max(0, $startedAt->diffInSeconds($lastSeenAt)),
                    'heartbeat_count' => max(1, (int) $activity['heartbeat_count']),
                    'ip' => $activity['ip'] ?: null,
                    'device_type' => $activity['device_type'] ?: null,
                    'device_name' => $activity['device_name'] ?: null,
                    'operating_system' => $activity['operating_system'] ?: null,
                    'browser' => $activity['browser'] ?: null,
                ],
            );
            $flushed++;
        }

        $this->info("Đã tổng hợp {$flushed} phiên hoạt động.");

        return self::SUCCESS;
    }
}
