<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\StaffGuard;

final class ResetUserTwoFactorAction
{
    use AsAction;

    public function handle(User $actor, User $target): void
    {
        StaffGuard::assertCanManage($actor, $target);

        $beforeSnapshot = AuditSnapshot::user($target);

        if ($target->twoFactorSecret !== null) {
            $target->twoFactorSecret->delete();
            $target->unsetRelation('twoFactorSecret');
        }

        $afterSnapshot = AuditSnapshot::user($target);

        Auditor::record(
            AuditAction::UserTwoFactorReset,
            $actor,
            $target,
            $beforeSnapshot,
            $afterSnapshot,
            metadata: ['action' => 'admin_reset_2fa'],
        );
    }
}
