<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;

/**
 * Start (or restart) TOTP enrollment: store an unconfirmed secret.
 *
 * @return array{secret: string, qr: string}
 */
final class BeginTwoFactorSetupAction
{
    use AsAction;

    public function __construct(
        private readonly TotpService $totp,
    ) {}

    /**
     * @return array{secret: string, qr: string}
     */
    public function handle(User $user): array
    {
        $secret = $this->totp->generateSecret();

        TwoFactorSecret::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'secret' => $secret,
                'recovery_codes' => null,
                'confirmed_at' => null,
            ],
        );

        return [
            'secret' => $secret,
            'qr' => $this->totp->qrDataUri((string) config('app.name'), $user->email, $secret),
        ];
    }
}
