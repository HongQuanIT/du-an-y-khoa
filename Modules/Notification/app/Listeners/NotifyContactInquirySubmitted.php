<?php

declare(strict_types=1);

namespace Modules\Notification\Listeners;

use App\Events\ContactInquirySubmitted;
use App\Models\User;
use App\Support\Auth\Staff;
use App\Support\Enums\Permission;
use Modules\Notification\Actions\CreateUserNotificationAction;

final class NotifyContactInquirySubmitted
{
    public function __construct(private readonly CreateUserNotificationAction $notify) {}

    public function handle(ContactInquirySubmitted $event): void
    {
        $inquiry = $event->inquiry;
        $staff = User::permission(Permission::ContactView->value)->get();
        $url = route('admin.contacts.show', $inquiry);

        foreach ($staff as $admin) {
            if (! Staff::isStaff($admin)) {
                continue;
            }

            $this->notify->handle(
                user: $admin,
                type: 'contact.new',
                title: 'Liên hệ mới từ form',
                body: sprintf(
                    '%s · %s: %s',
                    $inquiry->reference,
                    $inquiry->subject->label(),
                    mb_strimwidth($inquiry->message, 0, 80, '…'),
                ),
                data: [
                    'contact_inquiry_id' => $inquiry->getKey(),
                    'reference' => $inquiry->reference,
                ],
                actionUrl: $url,
            );
        }
    }
}
