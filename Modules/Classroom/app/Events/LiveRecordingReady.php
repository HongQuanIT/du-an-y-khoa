<?php

declare(strict_types=1);

namespace Modules\Classroom\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Classroom\Models\LiveRecording;
use Modules\Classroom\Models\LiveSession;

final class LiveRecordingReady implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public LiveSession $session,
        public LiveRecording $recording,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('live-session.'.$this->session->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'recording.ready';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'status' => $this->recording->status->value,
            'playback_url' => route('classroom.live.recording', [
                'classroom' => $this->session->classroom,
                'liveSession' => $this->session,
            ]),
        ];
    }
}
