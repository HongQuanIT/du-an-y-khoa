<?php

declare(strict_types=1);

namespace Modules\Partner\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Partner\Enums\PartnerStatus;
use Modules\Partner\Models\Partner;
use Modules\Partner\Support\PartnerSettings;

final class EnsurePartnerProfileAction
{
    use AsAction;

    public function handle(User $user): Partner
    {
        return Partner::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'display_name' => $user->name,
                'default_commission_rate_bps' => PartnerSettings::defaultCommissionRateBps(),
                'status' => PartnerStatus::Active,
            ],
        );
    }
}
