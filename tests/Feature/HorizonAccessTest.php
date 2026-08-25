<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Support\AdminMenu;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Tests\TestCase;

final class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_sees_horizon_in_sidebar_and_can_view_gate(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $labels = collect(AdminMenu::for($admin))->pluck('label');
        $this->assertTrue($labels->contains('Horizon'));

        $this->assertTrue(Gate::forUser($admin)->check('viewHorizon'));

        $this->actingAsStaff($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Horizon', false)
            ->assertSee('/horizon', false);
    }

    public function test_student_and_instructor_cannot_view_horizon(): void
    {
        foreach ([Role::Student, Role::Instructor, Role::ContentEditor] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);

            $this->assertFalse(Gate::forUser($user)->check('viewHorizon'), $role->value);

            $labels = collect(AdminMenu::for($user))->pluck('label');
            $this->assertFalse($labels->contains('Horizon'), $role->value);

            $this->actingAs($user)
                ->get('/horizon')
                ->assertForbidden();
        }
    }

    public function test_guest_is_redirected_from_horizon(): void
    {
        $this->get('/horizon')->assertRedirect();
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->enrollTwoFactor($user);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }

    private function enrollTwoFactor(User $user): void
    {
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);
    }
}
