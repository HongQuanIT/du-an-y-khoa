<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Services\SettingService;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\Billing\Support\GatewaySettings;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

final class AdminBillingGatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_gateway_settings_page(): void
    {
        $admin = $this->staffUser(Role::Admin->value);

        $this->actingAsStaff($admin)
            ->get(route('admin.billing.gateways.index'))
            ->assertOk()
            ->assertSee('Cổng thanh toán')
            ->assertSee('VNPay')
            ->assertSee('MoMo')
            ->assertSee('ZaloPay')
            ->assertSee('Fake Gateway');
    }

    public function test_admin_can_update_vnpay_and_default_gateway(): void
    {
        $admin = $this->staffUser(Role::Admin->value);

        $this->actingAsStaff($admin)
            ->put(route('admin.billing.gateways.update'), [
                'default_gateway' => 'vnpay',
                'gateways' => [
                    'fake' => ['enabled' => '1'],
                    'vnpay' => [
                        'enabled' => '1',
                        'tmn_code' => 'DEMO_TMN',
                        'hash_secret' => 'secret-value-xyz',
                        'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
                    ],
                    'momo' => [
                        'enabled' => '0',
                        'partner_code' => 'MOMO_PARTNER',
                        'access_key' => 'momo-access',
                        'secret_key' => 'momo-secret',
                        'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
                    ],
                    'zalopay' => [
                        'enabled' => '0',
                        'app_id' => '',
                        'key1' => '',
                        'key2' => '',
                        'endpoint' => 'https://sb-openapi.zalopay.vn/v2/create',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.billing.gateways.index'));

        $settings = app(GatewaySettings::class);

        $this->assertSame('vnpay', $settings->defaultGateway());
        $this->assertTrue($settings->isReady('vnpay'));
        $this->assertSame('DEMO_TMN', $settings->get('vnpay', 'tmn_code'));
        $this->assertSame('secret-value-xyz', $settings->get('vnpay', 'hash_secret'));
        $this->assertSame('MOMO_PARTNER', $settings->get('momo', 'partner_code'));
        $this->assertFalse($settings->isReady('momo')); // not implemented yet
        $this->assertContains('vnpay', $settings->availableForCheckout());
    }

    public function test_blank_secret_keeps_existing_value(): void
    {
        $admin = $this->staffUser(Role::Admin->value);
        app(SettingService::class)->set('billing_gateways.vnpay_hash_secret', 'keep-me', 'string');
        app(SettingService::class)->set('billing_gateways.vnpay_tmn_code', 'CODE1', 'string');
        app(SettingService::class)->set('billing_gateways.vnpay_enabled', true, 'boolean');
        app(SettingService::class)->set('billing_gateways.vnpay_url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html', 'string');

        $this->actingAsStaff($admin)
            ->put(route('admin.billing.gateways.update'), [
                'default_gateway' => 'fake',
                'gateways' => [
                    'fake' => ['enabled' => '1'],
                    'vnpay' => [
                        'enabled' => '1',
                        'tmn_code' => 'CODE1',
                        'hash_secret' => '',
                        'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
                    ],
                    'momo' => ['enabled' => '0', 'partner_code' => '', 'access_key' => '', 'secret_key' => '', 'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create'],
                    'zalopay' => ['enabled' => '0', 'app_id' => '', 'key1' => '', 'key2' => '', 'endpoint' => 'https://sb-openapi.zalopay.vn/v2/create'],
                ],
            ])
            ->assertRedirect(route('admin.billing.gateways.index'));

        $this->assertSame('keep-me', app(GatewaySettings::class)->get('vnpay', 'hash_secret'));
    }

    public function test_student_cannot_access_gateway_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);

        $this->actingAs($user)
            ->get(route('admin.billing.gateways.index'))
            ->assertForbidden();
    }

    private function staffUser(string $role): User
    {
        SpatiePermission::findOrCreate(Permission::BillingManage->value, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);
        $user->givePermissionTo(Permission::BillingManage->value);

        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }
}
