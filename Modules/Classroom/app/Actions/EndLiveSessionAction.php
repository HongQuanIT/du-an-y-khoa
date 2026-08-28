<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Support\Concerns\AsAction;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MessageType;
use Modules\Classroom\Events\LiveSessionEnded;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionMessage;

final class EndLiveSessionAction
{
    use AsAction;

    public function handle(Classroom $classroom, LiveSession $session): LiveSession
    {
        if ($session->classroom_id !== $classroom->getKey()) {
            abort(404);
        }

        if ($session->status !== LiveSessionStatus::Live) {
            return $session;
        }

        $session->update([
            'status' => LiveSessionStatus::Ended,
            'ended_at' => now(),
        ]);

        LiveSessionMessage::create([
            'live_session_id' => $session->getKey(),
            'user_id' => $classroom->host_user_id,
            'body' => 'Buổi live đã kết thúc. Chat chỉ còn xem.',
            'type' => MessageType::System,
            'created_at' => now(),
        ]);

        event(new LiveSessionEnded($session->fresh() ?? $session));

        return $session->fresh() ?? $session;
    }
}
