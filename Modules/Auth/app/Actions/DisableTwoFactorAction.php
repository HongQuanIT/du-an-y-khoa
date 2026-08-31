<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Auth\TwoFactorTrustedDevice;
use App\Support\Auth\TwoFactorSession;
use App\Support\Concerns\AsAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Opt-out of TOTP: require current password, delete secret, clear 2FA session.
 */
final class DisableTwoFactorAction
{
    use AsAction;

    public function handle(User $user, string $password, Request $request): void
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'current_password' => 'Tài khoản chưa bật xác thực hai bước.',
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Mật khẩu hiện tại không đúng.',
            ]);
        }

        $user->twoFactorSecret?->delete();
        $user->unsetRelation('twoFactorSecret');

        TwoFactorSession::clear($request);
        TwoFactorTrustedDevice::forget();
        Auditor::record(AuditAction::AuthTwoFactorDisabled, $user, $user, request: $request);
    }
}
