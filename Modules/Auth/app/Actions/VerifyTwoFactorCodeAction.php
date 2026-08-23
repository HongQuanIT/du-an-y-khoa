<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Contracts\Encryption\DecryptException;
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
        try {
            $record = $user->twoFactorSecret;
        } catch (DecryptException) {
            throw ValidationException::withMessages([
                'code' => 'Dữ liệu 2FA không đọc được. Vui lòng thiết lập lại xác thực hai bước.',
            ]);
        }

        if ($record === null || ! $record->isConfirmed()) {
            throw ValidationException::withMessages([
                'code' => 'Tài khoản chưa bật 2FA.',
            ]);
        }

        try {
            if ($this->totp->verify($record->secret, preg_replace('/\D+/', '', $code) ?: '')) {
                return;
            }
        } catch (DecryptException) {
            throw ValidationException::withMessages([
                'code' => 'Dữ liệu 2FA không đọc được. Vui lòng thiết lập lại xác thực hai bước.',
            ]);
        }

        $normalized = strtoupper(str_replace([' ', '-'], '', $code));
        try {
            $hashes = $record->recovery_codes ?? [];
        } catch (DecryptException) {
            throw ValidationException::withMessages([
                'code' => 'Dữ liệu 2FA không đọc được. Vui lòng thiết lập lại xác thực hai bước.',
            ]);
        }

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
