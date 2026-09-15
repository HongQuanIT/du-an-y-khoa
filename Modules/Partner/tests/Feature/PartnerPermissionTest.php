<?php

declare(strict_types=1);

namespace Modules\Partner\Tests\Feature;

use App\Models\User;
use App\Support\Auth\HomePath;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PartnerPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_screen_requires_its_permission_and_menu_matches(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = \Spatie\Permission\Models\Role::findByName(Role::Partner->value);
        $role->syncPermissions(['partner.portal']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $screens = [
            'partner_dashboard.view' => 'partner.dashboard',
            'partner_code.view' => 'partner.codes.index',
            'partner_referral.view' => 'partner.referrals.index',
            'partner_commission.view' => 'partner.commissions.index',
            'partner_payout.view' => 'partner.payouts.index',
        ];

        foreach ($screens as $permission => $route) {
            $this->actingAsWithWebSession($user)->get(route($route))->assertForbidden();
            $user->givePermissionTo($permission);
            $response = $this->actingAsWithWebSession($user)->get(route($route))->assertOk();
            foreach ($screens as $otherRoute) {
                if ($otherRoute !== $route) {
                    $response->assertDontSee('href="'.route($otherRoute).'"', false);
                }
            }
            $this->assertSame(route($route, absolute: false), HomePath::for($user));
            $user->revokePermissionTo($permission);
            $this->actingAsWithWebSession($user)->get(route($route))->assertForbidden();
        }

        $user->givePermissionTo(array_keys($screens));
        $role->revokePermissionTo('partner.portal');
        $user->unsetRelation('roles');
        foreach ($screens as $route) {
            $this->actingAsWithWebSession($user)->get(route($route))->assertForbidden();
        }
    }
}
