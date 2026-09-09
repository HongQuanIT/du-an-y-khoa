<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Data\RegisterData;
use Modules\Auth\Models\LearnerProfile;
use Modules\Partner\Actions\AttributePartnerReferralAction;
use Modules\Partner\Enums\AttributionSource;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Use case: create a self-service learner account.
 *
 * Logging the new user in stays in the HTTP layer; this action owns the user
 * record and its default role.
 */
final class RegisterUserAction
{
    use AsAction;

    public function __construct(
        private readonly AttributePartnerReferralAction $attributeReferral,
    ) {}

    public function handle(RegisterData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'locale' => app()->getLocale(),
            ]);

            RoleModel::findOrCreate(Role::Student->value, 'web');
            $user->assignRole(Role::Student->value);

            LearnerProfile::query()->create([
                'user_id' => $user->getKey(),
                'registration_method' => $data->registrationMethod,
                'utm_source' => $data->attribution['utm_source'] ?? null,
                'utm_medium' => $data->attribution['utm_medium'] ?? null,
                'utm_campaign' => $data->attribution['utm_campaign'] ?? null,
                'utm_content' => $data->attribution['utm_content'] ?? null,
                'referrer_url' => $data->attribution['referrer_url'] ?? null,
                'landing_page' => $data->attribution['landing_page'] ?? null,
            ]);

            if ($data->inviteCode !== null) {
                $this->attributeReferral->handle(
                    $user,
                    $data->inviteCode,
                    $data->inviteFromField ? AttributionSource::CodeField : AttributionSource::Link,
                );
            }

            return $user;
        });

        event(new Registered($user));
        Auditor::record(AuditAction::AuthRegistered, $user, $user);

        return $user;
    }
}
