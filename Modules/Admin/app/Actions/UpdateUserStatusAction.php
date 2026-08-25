<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\UserStatus;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\StaffGuard;

final class UpdateUserStatusAction
{
    use AsAction;

    public function handle(User $actor, User $target, UserStatus $status, ?string $reason = null): User
    {
        StaffGuard::assertCanManage($actor, $target);

        $before = AuditSnapshot::user($target);

        $target->forceFill(['status' => $status])->save();

        Auditor::record(
            AuditAction::UserStatusChanged,
            $actor,
            $target,
            $before,
            AuditSnapshot::user($target),
            metadata: ['reason' => $reason],
        );

        return $target->refresh();
    }
}
