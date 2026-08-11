<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\InstitutionMember;
use Modules\Billing\Models\Subscription;

final class RenewInstitutionLicenseAction
{
    use AsAction;

    public function handle(User $user, int $memberId): InstitutionMember
    {
        return DB::transaction(function () use ($user, $memberId): InstitutionMember {
            /** @var InstitutionMember|null $member */
            $member = InstitutionMember::query()
                ->with(['institution.plan'])
                ->where('user_id', $user->getKey())
                ->whereKey($memberId)
                ->lockForUpdate()
                ->first();

            if ($member === null) {
                throw ValidationException::withMessages([
                    'org_license' => 'Giấy phép tổ chức không tồn tại.',
                ]);
            }

            $institution = $member->institution;
            if ($institution === null || ! $institution->isValid()) {
                throw ValidationException::withMessages([
                    'org_license' => 'Giấy phép tổ chức đã hết hạn hoặc không còn hiệu lực.',
                ]);
            }

            if (! $institution->matchesEmail($member->email)) {
                throw ValidationException::withMessages([
                    'org_license' => 'Email không còn thuộc tổ chức này.',
                ]);
            }

            $now = Carbon::now();
            $member->forceFill([
                'status' => 'verified',
                'verified_at' => $now,
            ])->save();

            if ($institution->plan !== null) {
                Subscription::query()->updateOrCreate(
                    [
                        'user_id' => $user->getKey(),
                        'plan_id' => $institution->plan->getKey(),
                        'source' => 'institution',
                    ],
                    [
                        'status' => 'active',
                        'starts_at' => $now,
                        'ends_at' => $institution->valid_until,
                    ],
                );
            }

            return $member->fresh(['institution']);
        });
    }
}
