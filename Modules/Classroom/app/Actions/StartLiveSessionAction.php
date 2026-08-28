<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MessageType;
use Modules\Classroom\Events\LiveSessionStarted;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionMessage;

final class StartLiveSessionAction
{
    use AsAction;

    public function handle(Classroom $classroom, LiveSession $session, bool $allowReopen = false): LiveSession
    {
        if ($session->classroom_id !== $classroom->getKey()) {
            abort(404);
        }

        if ($session->status === LiveSessionStatus::Live) {
            return $session;
        }

        $isReopening = $session->status === LiveSessionStatus::Ended;

        if ($session->status !== LiveSessionStatus::Scheduled && ! ($allowReopen && $isReopening)) {
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
            'started_at' => $session->started_at ?? now(),
            'ended_at' => null,
            'livekit_room_name' => $session->livekit_room_name ?: ('classroom-'.$session->uuid),
        ]);

        LiveSessionMessage::create([
            'live_session_id' => $session->getKey(),
            'user_id' => $classroom->host_user_id,
            'body' => $isReopening ? 'Buổi live đã được mở lại.' : 'Buổi live đã bắt đầu.',
            'type' => MessageType::System,
            'created_at' => now(),
        ]);

        $fresh = $session->fresh() ?? $session;
        event(new LiveSessionStarted($fresh));

        return $fresh;
    }
}
