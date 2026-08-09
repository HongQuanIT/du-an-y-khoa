<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Admin\Support\Auditor;
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

        $target->forceFill(['email_verified_at' => now()])->save();

        Auditor::record(
            'admin.user.email_verified',
            $actor,
            $target,
            ['email_verified_at' => null],
            ['email_verified_at' => $target->email_verified_at?->toIso8601String()],
        );

        return $target->refresh();
    }
}
