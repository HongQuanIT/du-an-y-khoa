<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

final class ActivityTracker
{
    private const INDEX_KEY = 'audit:activity:stale';

    public function heartbeat(User $user, string $sessionId, string $area, Request $request): void
    {
        $now = now()->timestamp;
        $client = UserAgentParser::parse($request->userAgent());
        $key = $this->key((int) $user->getKey(), $sessionId, $area);
        $payload = [
            'user_id' => (string) $user->getKey(),
            'session_id' => $sessionId,
            'area' => $area,
            'portal' => $this->portal($area),
            'last_seen_at' => (string) $now,
            'ip' => (string) ($request->ip() ?? ''),
            'device_type' => (string) ($client['device_type'] ?? ''),
            'device_name' => (string) ($client['device_name'] ?? ''),
            'operating_system' => (string) ($client['operating_system'] ?? ''),
            'browser' => (string) ($client['browser'] ?? ''),
        ];

        $arguments = [$key, self::INDEX_KEY, (string) $now, $key];
        foreach ($payload as $field => $value) {
            $arguments[] = $field;
            $arguments[] = $value;
        }

        Redis::connection((string) config('audit.activity.redis_connection', 'default'))->eval(
            <<<'LUA'
                local key = KEYS[1]
                local index = KEYS[2]
                local now = ARGV[1]
                if redis.call('EXISTS', key) == 0 then
                    redis.call('HSET', key, 'started_at', now, 'heartbeat_count', 0)
                end
                for i = 3, #ARGV, 2 do
                    redis.call('HSET', key, ARGV[i], ARGV[i + 1])
                end
                redis.call('HINCRBY', key, 'heartbeat_count', 1)
                redis.call('EXPIRE', key, 86400)
                redis.call('ZADD', index, now, ARGV[2])
                return 1
                LUA,
            2,
            ...$arguments,
        );
    }

    public static function indexKey(): string
    {
        return self::INDEX_KEY;
    }

    private function key(int $userId, string $sessionId, string $area): string
    {
        return 'audit:activity:'.$userId.':'.$sessionId.':'.sha1($area);
    }

    private function portal(string $area): string
    {
        return match (true) {
            $area === '/admin', str_starts_with($area, '/admin/') => 'admin',
            $area === '/teach', str_starts_with($area, '/teach/') => 'teach',
            default => 'student',
        };
    }
}
