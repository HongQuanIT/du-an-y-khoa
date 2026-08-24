<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\Institution;
use Modules\Billing\Models\InstitutionMember;
use Modules\Billing\Models\Subscription;

final class ActivateInstitutionLicenseAction
{
    use AsAction;

    /**
     * @return array{member: InstitutionMember, subscription: Subscription|null}
     */
    public function handle(User $user, string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'institution_email' => 'Email tổ chức không hợp lệ.',
            ]);
        }

        return DB::transaction(function () use ($user, $email): array {
            /** @var Institution|null $institution */
            $institution = Institution::query()
                ->with('plan')
                ->where('is_active', true)
                ->get()
                ->first(fn (Institution $candidate): bool => $candidate->matchesEmail($email));

            if ($institution === null || ! $institution->isValid()) {
                throw ValidationException::withMessages([
                    'institution_email' => 'Không tìm thấy giấy phép tổ chức cho email này.',
                ]);
            }

            $now = Carbon::now();

            $member = InstitutionMember::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'institution_id' => $institution->getKey(),
                ],
                [
                    'email' => $email,
                    'status' => 'verified',
                    'verified_at' => $now,
                ],
            );

            $subscription = null;
            if ($institution->plan !== null) {
                $subscription = Subscription::query()
                    ->where('user_id', $user->getKey())
                    ->where('plan_id', $institution->plan->getKey())
                    ->where('source', 'institution')
                    ->where('status', 'active')
                    ->where(function ($query) use ($institution): void {
                        $query->whereNull('ends_at');
                        if ($institution->valid_until !== null) {
                            $query->orWhere('ends_at', '>=', $institution->valid_until);
                        }
                    })
                    ->first();

                if ($subscription === null) {
                    $subscription = Subscription::query()->create([
                        'user_id' => $user->getKey(),
                        'plan_id' => $institution->plan->getKey(),
                        'status' => 'active',
                        'source' => 'institution',
                        'starts_at' => $now,
                        'ends_at' => $institution->valid_until,
                    ]);
                } else {
                    $subscription->forceFill([
                        'ends_at' => $institution->valid_until,
                        'status' => 'active',
                    ])->save();
                }
            }

            InvalidateEntitlementCacheAction::run((int) $user->getKey());

            return [
                'member' => $member->load('institution'),
                'subscription' => $subscription?->load('plan'),
            ];
        });
    }
}
