<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Services\TotpService;

/**
 * Verify a TOTP code or a one-time recovery code for an enrolled user.
 */
final class VerifyTwoFactorCodeAction
{
    use AsAction;

    public function __construct(
        private readonly TotpService $totp,
    ) {}

    public function handle(User $user, string $code): void
    {
        $record = $user->twoFactorSecret;

        if ($record === null || ! $record->isConfirmed()) {
            throw ValidationException::withMessages([
                'code' => 'Tài khoản chưa bật 2FA.',
            ]);
        }

        if ($this->totp->verify($record->secret, preg_replace('/\D+/', '', $code) ?: '')) {
            return;
        }

        $normalized = strtoupper(str_replace([' ', '-'], '', $code));
        $hashes = $record->recovery_codes ?? [];

        foreach ($hashes as $index => $hash) {
            if ($normalized !== '' && Hash::check($normalized, $hash)) {
                unset($hashes[$index]);
                $record->forceFill([
                    'recovery_codes' => array_values($hashes),
                ])->save();

                return;
            }
        }

        throw ValidationException::withMessages([
            'code' => 'Mã xác thực hoặc mã khôi phục không đúng.',
        ]);
    }
}
