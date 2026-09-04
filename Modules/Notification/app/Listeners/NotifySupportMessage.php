<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use App\Events\SupportMessageCreated;
use App\Models\User;
use App\Support\Auth\Staff;
use App\Support\Enums\Permission;
use Modules\Notification\Actions\CreateUserNotificationAction;

/**
 * - Staff reply → notify conversation owner (personalized).
 * - User message needing admin → notify staff with SupportManage (inbox hint).
 */
final class NotifySupportMessage
{
    public function __construct(private readonly CreateUserNotificationAction $notify) {}

    public function handle(SupportMessageCreated $event): void
    {
        $message = $event->message->loadMissing('conversation');
        $conversation = $message->conversation;
        if ($conversation === null) {
            return;
        }

        if ($message->sender_type === 'admin') {
            $owner = User::query()->find($conversation->user_id);
            if ($owner === null) {
                return;
            }

            $this->notify->handle(
                user: $owner,
                type: 'support.reply',
                title: 'Phản hồi hỗ trợ',
                body: mb_strimwidth((string) $message->body, 0, 120, '…'),
                data: [
                    'conversation_id' => $conversation->getKey(),
                ],
                actionUrl: route('support.index', ['conversation' => $conversation->getKey()]),
            );

            return;
        }

        if ($message->sender_type !== 'user' || ! $conversation->needsAdminReply()) {
            return;
        }

        $staff = User::permission(Permission::SupportManage->value)->get();
        $url = route('admin.support.show', $conversation);

        foreach ($staff as $admin) {
            if (! Staff::isStaff($admin)) {
                continue;
            }

            $this->notify->handle(
                user: $admin,
                type: 'support.waiting',
                title: 'Hỗ trợ chờ xử lý',
                body: sprintf(
                    '%s: %s',
                    $conversation->subject ?: 'Yêu cầu hỗ trợ',
                    mb_strimwidth((string) $message->body, 0, 80, '…'),
                ),
                data: [
                    'conversation_id' => $conversation->getKey(),
                ],
                actionUrl: $url,
            );
        }
    }
}
