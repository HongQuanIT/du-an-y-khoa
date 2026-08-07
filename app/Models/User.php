<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Enums\Entitlement;
use App\Support\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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
        if ($this->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value, Role::ContentEditor->value])) {
            return Entitlement::values();
        }

        // Billing module resolves these from the active subscription/plan.
        $entitlements = [];

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
}
