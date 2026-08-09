<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\UserStatus;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\StaffGuard;

final class UpdateUserStatusAction
{
    use AsAction;

    public function handle(User $actor, User $target, UserStatus $status, ?string $reason = null): User
    {
        StaffGuard::assertCanManage($actor, $target);

        $before = ['status' => $target->status?->value ?? UserStatus::Active->value];

        $target->forceFill(['status' => $status])->save();

        Auditor::record(
            'admin.user.status_change',
            $actor,
            $target,
            $before,
            [
                'status' => $status->value,
                'reason' => $reason,
            ],
        );

        return $target->refresh();
    }
}
