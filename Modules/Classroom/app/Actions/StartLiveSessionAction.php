<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MessageType;
use Modules\Classroom\Enums\RecordingStatus;
use Modules\Classroom\Events\LiveSessionStarted;
use Modules\Classroom\Models\LiveRecording;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionMessage;
use Modules\Classroom\Services\LiveKitEgressService;

final class StartLiveSessionAction
{
    use AsAction;

    public function __construct(private readonly LiveKitEgressService $egress) {}

    public function handle(Classroom $classroom, LiveSession $session): LiveSession
    {
        if ($session->classroom_id !== $classroom->getKey()) {
            abort(404);
        }

        if ($session->status === LiveSessionStatus::Live) {
            return $session;
        }

        if ($session->status !== LiveSessionStatus::Scheduled) {
            throw ValidationException::withMessages([
                'session' => 'Không thể bắt đầu buổi này.',
            ]);
        }

        $alreadyLive = $classroom->sessions()
            ->where('status', LiveSessionStatus::Live->value)
            ->whereKeyNot($session->getKey())
            ->exists();

        if ($alreadyLive) {
            throw ValidationException::withMessages([
                'session' => 'Lớp đang có buổi live khác. Hãy kết thúc trước.',
            ]);
        }

        $session->update([
            'status' => LiveSessionStatus::Live,
            'started_at' => now(),
            'ended_at' => null,
            'livekit_room_name' => $session->livekit_room_name ?: ('classroom-'.$session->uuid),
        ]);

        LiveSessionMessage::create([
            'live_session_id' => $session->getKey(),
            'user_id' => $classroom->host_user_id,
            'body' => 'Buổi live đã bắt đầu.',
            'type' => MessageType::System,
            'created_at' => now(),
        ]);

        if ($this->egress->isEnabled()) {
            try {
                $egressId = $this->egress->startForSession($session);
                if ($egressId !== null) {
                    LiveRecording::create([
                        'live_session_id' => $session->getKey(),
                        'status' => RecordingStatus::Processing,
                        'egress_id' => $egressId,
                    ]);
                }
            } catch (\Throwable) {
                // Live continues even if recording fails to start
            }
        }

        $fresh = $session->fresh() ?? $session;
        event(new LiveSessionStarted($fresh));

        return $fresh;
    }
}
