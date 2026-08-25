<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\StaffGuard;

final class SendUserPasswordResetAction
{
    use AsAction;

    public function handle(User $actor, User $target): void
    {
        StaffGuard::assertCanManage($actor, $target);

        $status = Password::broker()->sendResetLink(['email' => $target->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        Auditor::record(
            AuditAction::UserPasswordResetRequested,
            $actor,
            $target,
            AuditSnapshot::user($target),
            AuditSnapshot::user($target),
            metadata: ['delivery_channel' => 'email'],
        );
    }
}
