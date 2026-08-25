<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Services\TotpService;

/**
 * Confirm TOTP with a first code; persist hashed recovery codes; return plain codes once.
 *
 * @return list<string>
 */
final class ConfirmTwoFactorSetupAction
{
    use AsAction;

    public function __construct(
        private readonly TotpService $totp,
    ) {}

    /**
     * @return list<string>
     */
    public function handle(User $user, string $code): array
    {
        $record = $user->twoFactorSecret;

        if ($record === null || $record->isConfirmed()) {
            throw ValidationException::withMessages([
                'code' => 'Phiên thiết lập 2FA không hợp lệ. Hãy bắt đầu lại.',
            ]);
        }

        if (! $this->totp->verify($record->secret, $code)) {
            throw ValidationException::withMessages([
                'code' => 'Mã xác thực không đúng.',
            ]);
        }

        $plainCodes = [];
        $hashed = [];

        for ($i = 0; $i < 8; $i++) {
            $raw = Str::upper(Str::random(8));
            $plainCodes[] = substr($raw, 0, 4).'-'.substr($raw, 4);
            $hashed[] = Hash::make($raw);
        }

        $record->forceFill([
            'recovery_codes' => $hashed,
            'confirmed_at' => now(),
        ])->save();

        Auditor::record(AuditAction::AuthTwoFactorEnabled, $user, $user);

        return $plainCodes;
    }
}
