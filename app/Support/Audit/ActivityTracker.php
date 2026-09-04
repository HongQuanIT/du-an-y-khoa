<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\User;
use App\Models\UserActivitySession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

/**
 * Presence tracking: start on open, leave on pagehide.
 * Same normalized screen within the coalesce window updates one row (refresh-safe).
 */
final class ActivityTracker
{
    private const INDEX_KEY = 'audit:activity:stale';

    public function start(User $user, string $sessionId, string $area, Request $request): void
    {
        $area = ActivityArea::normalize($area);
        if (ActivityArea::shouldIgnore($area)) {
            return;
        }

        $this->touchRedis($user, $sessionId, $area, $request, createIfMissing: true);
    }

    public function leave(
        User $user,
        string $sessionId,
        string $area,
        Request $request,
    ): void {
        $area = ActivityArea::normalize($area);
        if (ActivityArea::shouldIgnore($area)) {
            return;
        }

        $now = now();
        $client = UserAgentParser::parse($request->userAgent());
        $redis = Redis::connection((string) config('audit.activity.redis_connection', 'default'));
        $key = $this->key((int) $user->getKey(), $sessionId, $area);

        $existing = $redis->hgetall($key);
        $startedAt = isset($existing['started_at'])
            ? Carbon::createFromTimestamp((int) $existing['started_at'])
            : $now;

        $this->persistVisit(
            userId: (int) $user->getKey(),
            sessionId: $sessionId,
            area: $area,
            portal: $this->portal($area),
            startedAt: $startedAt,
            lastSeenAt: $now,
            heartbeatCount: max(1, (int) ($existing['heartbeat_count'] ?? 1)),
            ip: $request->ip() ?: ($existing['ip'] ?? null),
            deviceType: $client['device_type'] ?? ($existing['device_type'] ?? null),
            deviceName: $client['device_name'] ?? ($existing['device_name'] ?? null),
            operatingSystem: $client['operating_system'] ?? ($existing['operating_system'] ?? null),
            browser: $client['browser'] ?? ($existing['browser'] ?? null),
        );

        $redis->del($key);
        $redis->zrem(self::INDEX_KEY, $key);
    }

    /**
     * @param  array<string, mixed>  $activity  Redis hash fields
     */
    public function persistFromRedisHash(array $activity): void
    {
        $area = ActivityArea::normalize((string) ($activity['area'] ?? '/'));
        if (ActivityArea::shouldIgnore($area)) {
            return;
        }

        $startedAt = Carbon::createFromTimestamp((int) $activity['started_at']);
        $lastSeenAt = Carbon::createFromTimestamp((int) $activity['last_seen_at']);

        $this->persistVisit(
            userId: (int) $activity['user_id'],
            sessionId: (string) $activity['session_id'],
            area: $area,
            portal: (string) ($activity['portal'] ?: $this->portal($area)),
            startedAt: $startedAt,
            lastSeenAt: $lastSeenAt,
            heartbeatCount: max(1, (int) ($activity['heartbeat_count'] ?? 1)),
            ip: ($activity['ip'] ?? null) ?: null,
            deviceType: ($activity['device_type'] ?? null) ?: null,
            deviceName: ($activity['device_name'] ?? null) ?: null,
            operatingSystem: ($activity['operating_system'] ?? null) ?: null,
            browser: ($activity['browser'] ?? null) ?: null,
        );
    }

    public static function indexKey(): string
    {
        return self::INDEX_KEY;
    }

    private function persistVisit(
        int $userId,
        string $sessionId,
        string $area,
        string $portal,
        Carbon $startedAt,
        Carbon $lastSeenAt,
        int $heartbeatCount,
        ?string $ip,
        ?string $deviceType,
        ?string $deviceName,
        ?string $operatingSystem,
        ?string $browser,
    ): void {
        $windowMinutes = max(1, (int) config('audit.activity.coalesce_minutes', 30));

        $recent = UserActivitySession::query()
            ->where('user_id', $userId)
            ->where('area', $area)
            ->where('last_seen_at', '>=', $lastSeenAt->copy()->subMinutes($windowMinutes))
            ->orderByDesc('last_seen_at')
            ->first();

        if ($recent !== null) {
            $recent->update([
                'session_id' => $sessionId,
                'portal' => $portal,
                'last_seen_at' => $lastSeenAt,
                'duration_seconds' => 0,
                'heartbeat_count' => max((int) $recent->heartbeat_count, $heartbeatCount),
                'ip' => $ip,
                'device_type' => $deviceType,
                'device_name' => $deviceName,
                'operating_system' => $operatingSystem,
                'browser' => $browser,
            ]);

            return;
        }

        UserActivitySession::query()->create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'area' => $area,
            'portal' => $portal,
            'started_at' => $startedAt,
            'last_seen_at' => $lastSeenAt,
            'duration_seconds' => 0,
            'heartbeat_count' => $heartbeatCount,
            'ip' => $ip,
            'device_type' => $deviceType,
            'device_name' => $deviceName,
            'operating_system' => $operatingSystem,
            'browser' => $browser,
        ]);
    }

    private function touchRedis(
        User $user,
        string $sessionId,
        string $area,
        Request $request,
        bool $createIfMissing,
    ): void {
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

        $arguments = [$key, self::INDEX_KEY, (string) $now, $key, $createIfMissing ? '1' : '0'];
        foreach ($payload as $field => $value) {
            $arguments[] = $field;
            $arguments[] = $value;
        }

        Redis::connection((string) config('audit.activity.redis_connection', 'default'))->eval(
            <<<'LUA'
                local key = KEYS[1]
                local index = KEYS[2]
                local now = ARGV[1]
                local create = ARGV[3] == '1'
                if redis.call('EXISTS', key) == 0 then
                    if not create then
                        return 0
                    end
                    redis.call('HSET', key, 'started_at', now, 'heartbeat_count', 1)
                end
                for i = 4, #ARGV, 2 do
                    redis.call('HSET', key, ARGV[i], ARGV[i + 1])
                end
                redis.call('EXPIRE', key, 86400)
                redis.call('ZADD', index, now, ARGV[2])
                return 1
                LUA,
            2,
            ...$arguments,
        );
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
            $area === '/partner', str_starts_with($area, '/partner/') => 'partner',
            default => 'student',
        };
    }
}
