<?php

declare(strict_types=1);

namespace Modules\Classroom\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionAttendanceSegment;

/**
 * Converts trusted LiveKit participant events into attendance intervals.
 * A reconnect never overwrites prior attendance; it creates another segment.
 */
final class LiveAttendanceService
{
    /** @param array<string, mixed> $event */
    public function handle(array $event): void
    {
        $eventName = strtolower((string) ($event['event'] ?? $event['type'] ?? ''));
        if (! in_array($eventName, ['participant_joined', 'participant_left'], true)) {
            return;
        }

        $roomName = data_get($event, 'room.name');
        $identity = data_get($event, 'participant.identity');
        if (! is_string($roomName) || ! is_string($identity)) {
            return;
        }

        $userId = $this->userIdFromIdentity($identity);
        if ($userId === null || ! User::query()->whereKey($userId)->exists()) {
            return;
        }

        DB::transaction(function () use ($eventName, $roomName, $userId, $event): void {
            $session = LiveSession::query()
                ->where('livekit_room_name', $roomName)
                ->where('status', LiveSessionStatus::Live->value)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                return;
            }

            if ($eventName === 'participant_joined') {
                $this->joined($session, $userId, $event);

                return;
            }

            $this->left($session, $userId);
        });
    }

    /** @param array<string, mixed> $event */
    private function joined(LiveSession $session, int $userId, array $event): void
    {
        $openSegment = $session->attendanceSegments()
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->lockForUpdate()
            ->first();

        if ($openSegment === null) {
            LiveSessionAttendanceSegment::create([
                'live_session_id' => $session->getKey(),
                'user_id' => $userId,
                'joined_at' => now(),
                'metadata' => [
                    'participant_sid' => data_get($event, 'participant.sid'),
                    'participant_name' => data_get($event, 'participant.name'),
                ],
            ]);
        }

        if ($session->classroom->host_user_id === $userId && $session->host_grace_until !== null) {
            $session->update(['host_grace_until' => null]);
        }
    }

    private function left(LiveSession $session, int $userId): void
    {
        $session->attendanceSegments()
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->update(['left_at' => now()]);

        if ($session->classroom->host_user_id === $userId) {
            $session->update([
                'host_grace_until' => now()->addSeconds(max(30, (int) config('classroom.livekit.host_grace_seconds', 300))),
            ]);
        }
    }

    private function userIdFromIdentity(string $identity): ?int
    {
        if (! preg_match('/^user-(\d+)$/', $identity, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
