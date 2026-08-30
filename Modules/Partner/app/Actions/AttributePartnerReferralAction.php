<?php

declare(strict_types=1);

namespace Modules\Partner\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Partner\Enums\AttributionSource;
use Modules\Partner\Models\PartnerAttribution;
use Modules\Partner\Models\PartnerInviteCode;
use Modules\Partner\Support\PartnerInviteIntent;
use Modules\Partner\Support\PartnerSettings;

/**
 * First-touch attribution at registration (DB unique on referred_user_id).
 */
final class AttributePartnerReferralAction
{
    use AsAction;

    public function handle(
        User $referredUser,
        string $code,
        AttributionSource $source = AttributionSource::Link,
    ): ?PartnerAttribution {
        return DB::transaction(function () use ($referredUser, $code, $source): ?PartnerAttribution {
            if (PartnerAttribution::query()->where('referred_user_id', $referredUser->getKey())->exists()) {
                return null;
            }

            if (! PartnerInviteIntent::isValidCode($code)) {
                return null;
            }

            /** @var PartnerInviteCode|null $invite */
            $invite = PartnerInviteCode::query()
                ->with('partner.user')
                ->forCode($code)
                ->lockForUpdate()
                ->first();

            if ($invite === null || ! $invite->isCurrentlyValid()) {
                return null;
            }

            if (! PartnerSettings::allowSelfReferral()) {
                $partnerEmail = $invite->partner?->user?->email;
                if (is_string($partnerEmail) && strcasecmp($partnerEmail, $referredUser->email) === 0) {
                    return null;
                }

                if ((int) $invite->partner?->user_id === (int) $referredUser->getKey()) {
                    return null;
                }
            }

            $attribution = PartnerAttribution::query()->create([
                'partner_id' => $invite->partner_id,
                'invite_code_id' => $invite->getKey(),
                'referred_user_id' => $referredUser->getKey(),
                'attributed_at' => Carbon::now(),
                'source' => $source,
            ]);

            $invite->forceFill([
                'use_count' => $invite->use_count + 1,
            ])->save();

            return $attribution;
        });
    }
}
