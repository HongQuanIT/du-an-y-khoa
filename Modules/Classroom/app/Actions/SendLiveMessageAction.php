<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Enums\MessageType;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionMessage;

final class SendLiveMessageAction
{
    use AsAction;

    public function handle(LiveSession $session, User $user, string $body, MessageType $type = MessageType::Chat): LiveSessionMessage
    {
        if (! $session->allowsChatSend()) {
            throw ValidationException::withMessages([
                'body' => 'Buổi live đã kết thúc — không còn gửi tin được.',
            ]);
        }

        $member = $session->classroom->memberFor($user);

        if ($member === null || $member->status !== MemberStatus::Active) {
            throw ValidationException::withMessages([
                'body' => 'Bạn cần là thành viên lớp để chat.',
            ]);
        }

        if ($session->chat_muted && ! ($session->classroom->roleFor($user)?->canModerate() ?? false)) {
            throw ValidationException::withMessages([
                'body' => 'Host đã tắt chat.',
            ]);
        }

        $body = trim($body);

        if ($body === '' || mb_strlen($body) > 2000) {
            throw ValidationException::withMessages([
                'body' => 'Nội dung không hợp lệ.',
            ]);
        }

        return LiveSessionMessage::create([
            'live_session_id' => $session->getKey(),
            'user_id' => $user->getKey(),
            'body' => $body,
            'type' => $type,
            'created_at' => now(),
        ]);
    }
}
