<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\StaffGuard;

final class VerifyUserEmailAction
{
    use AsAction;

    public function handle(User $actor, User $target): User
    {
        StaffGuard::assertCanManage($actor, $target);

        if ($target->email_verified_at !== null) {
            return $target;
        }

        $before = AuditSnapshot::user($target);
        $target->forceFill(['email_verified_at' => now()])->save();

        Auditor::record(
            AuditAction::UserEmailVerified,
            $actor,
            $target,
            $before,
            AuditSnapshot::user($target),
        );

        return $target->refresh();
    }
}
