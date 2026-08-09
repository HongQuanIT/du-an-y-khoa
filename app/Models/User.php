<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Auth\Staff;
use App\Support\Enums\Entitlement;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Auth\Models\TwoFactorSecret;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'locale', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Premium entitlements currently granted to the user.
     *
     * Staff roles are entitled to everything; otherwise entitlements come from
     * the active subscription (Billing module provides them). Kept here so the
     * `subscription:` middleware has a stable contract to call.
     *
     * @return list<string>
     */
    public function entitlements(): array
    {
        if (Staff::isStaff($this)) {
            return Entitlement::values();
        }

        // Billing module resolves these from the active subscription/plan.
        $entitlements = [];

        // Instructor hosts via role on /teach (not Premium subscription).
        if ($this->hasRole(Role::Instructor->value)) {
            $entitlements[] = Entitlement::ClassroomHost->value;
        }

        // Dev/local: allow hosting classrooms before Billing plans grant Premium.
        if (config('classroom.open_hosting')) {
            $entitlements[] = Entitlement::ClassroomHost->value;
        }

        return array_values(array_unique($entitlements));
    }

    public function hasEntitlement(string $entitlement): bool
    {
        return in_array($entitlement, $this->entitlements(), true);
    }

    public function twoFactorSecret(): HasOne
    {
        return $this->hasOne(TwoFactorSecret::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->twoFactorSecret?->isConfirmed() === true;
    }

    public function primaryRoleName(): ?string
    {
        return $this->getRoleNames()->first();
    }

    public function isSuspendedOrBanned(): bool
    {
        $status = $this->status ?? UserStatus::Active;

        return ! $status->canAuthenticate();
    }
}
