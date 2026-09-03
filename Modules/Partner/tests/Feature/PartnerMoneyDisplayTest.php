<?php

declare(strict_types=1);

namespace Modules\Partner\Tests\Feature;

use App\Models\User;
use App\Services\SettingService;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\Payment;
use Modules\Partner\Actions\CreatePartnerPayoutAction;
use Modules\Partner\Actions\RecordPartnerCommissionAction;
use Modules\Partner\Enums\AttributionSource;
use Modules\Partner\Enums\CommissionStatus;
use Modules\Partner\Enums\PartnerStatus;
use Modules\Partner\Enums\PayoutStatus;
use Modules\Partner\Models\Partner;
use Modules\Partner\Models\PartnerAttribution;
use Modules\Partner\Models\PartnerCommission;
use Modules\Partner\Models\PartnerInviteCode;
use Modules\Partner\Models\PartnerPayout;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

final class PartnerMoneyDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatieRole::findOrCreate(Role::Admin->value, 'web');
        SpatieRole::findOrCreate(Role::Partner->value, 'web');
        SpatiePermission::findOrCreate(Permission::AdminPartnersManage->value, 'web');
        SpatiePermission::findOrCreate(Permission::AdminPartnersPayouts->value, 'web');
    }

    public function test_admin_partner_report_displays_billing_amounts_as_whole_vnd(): void
    {
        $this->createCommission(grossAmount: 1_790_000, commissionAmount: 358_000, rateBps: 2000);
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $admin->givePermissionTo(Permission::AdminPartnersManage->value);

        $this->actingAsWithWebSession($admin)
            ->get(route('admin.partners.index'))
            ->assertOk()
            ->assertSee('1.790.000₫')
            ->assertSee('358.000₫')
            ->assertDontSee('17.900₫')
            ->assertDontSee('3.580₫');
    }

    public function test_commission_calculation_keeps_billing_amount_as_whole_vnd(): void
    {
        $context = $this->createAttribution(rateBps: 2000);
        $payment = $this->createPayment(1_790_000);

        $commission = app(RecordPartnerCommissionAction::class)->handle(
            $payment,
            $context['referred_user']->getKey(),
        );

        $this->assertNotNull($commission);
        $this->assertSame(1_790_000, $commission->gross_cents);
        $this->assertSame(2000, $commission->rate_bps);
        $this->assertSame(358_000, $commission->commission_cents);
    }

    public function test_partner_commission_page_displays_billing_amounts_as_whole_vnd(): void
    {
        ['user' => $partnerUser] = $this->createCommission(
            grossAmount: 1_790_000,
            commissionAmount: 358_000,
            rateBps: 2000,
        );

        $this->actingAsWithWebSession($partnerUser)
            ->get(route('partner.commissions.index'))
            ->assertOk()
            ->assertSee('1.790.000₫')
            ->assertSee('358.000₫')
            ->assertDontSee('17.900₫')
            ->assertDontSee('3.580₫');
    }

    public function test_partner_dashboard_displays_commission_totals_as_whole_vnd(): void
    {
        ['user' => $partnerUser] = $this->createCommission(
            grossAmount: 1_790_000,
            commissionAmount: 358_000,
            rateBps: 2000,
        );

        $this->actingAsWithWebSession($partnerUser)
            ->get(route('partner.dashboard'))
            ->assertOk()
            ->assertSee('358.000₫')
            ->assertDontSee('3.580₫');
    }

    public function test_partner_payout_page_displays_amount_as_whole_vnd(): void
    {
        ['partner' => $partner, 'user' => $partnerUser] = $this->createCommission(
            grossAmount: 1_790_000,
            commissionAmount: 358_000,
            rateBps: 2000,
        );
        PartnerPayout::query()->create([
            'partner_id' => $partner->getKey(),
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->toDateString(),
            'amount_cents' => 358_000,
            'status' => PayoutStatus::Approved,
        ]);

        $this->actingAsWithWebSession($partnerUser)
            ->get(route('partner.payouts.index'))
            ->assertOk()
            ->assertSee('358.000₫')
            ->assertDontSee('3.580₫');
    }

    public function test_admin_payout_page_displays_amount_as_whole_vnd(): void
    {
        ['partner' => $partner] = $this->createCommission(
            grossAmount: 1_790_000,
            commissionAmount: 358_000,
            rateBps: 2000,
        );
        PartnerPayout::query()->create([
            'partner_id' => $partner->getKey(),
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->toDateString(),
            'amount_cents' => 358_000,
            'status' => PayoutStatus::Approved,
        ]);
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $admin->givePermissionTo(Permission::AdminPartnersPayouts->value);

        $this->actingAsWithWebSession($admin)
            ->get(route('admin.partners.payouts.index'))
            ->assertOk()
            ->assertSee('358.000₫')
            ->assertDontSee('3.580₫');
    }

    public function test_minimum_payout_validation_message_uses_whole_vnd(): void
    {
        ['partner' => $partner] = $this->createCommission(
            grossAmount: 500_000,
            commissionAmount: 50_000,
        );
        app(SettingService::class)->set('partner.min_payout_cents', 100_000, 'integer');

        try {
            app(CreatePartnerPayoutAction::class)->handle(
                partner: $partner,
                periodFrom: now()->startOfMonth(),
                periodTo: now()->endOfMonth(),
                actor: User::factory()->create(),
            );
            $this->fail('Expected the payout minimum validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Tổng hoa hồng (50.000₫) chưa đạt mức tối thiểu 100.000₫.',
                $exception->errors()['period'][0],
            );
        }
    }

    /**
     * @return array{partner: Partner, user: User, commission: PartnerCommission}
     */
    private function createCommission(int $grossAmount, int $commissionAmount, int $rateBps = 1000): array
    {
        $context = $this->createAttribution($rateBps);
        $payment = $this->createPayment($grossAmount);
        $commission = PartnerCommission::query()->create([
            'partner_id' => $context['partner']->getKey(),
            'attribution_id' => $context['attribution']->getKey(),
            'payment_id' => $payment->getKey(),
            'referred_user_id' => $context['referred_user']->getKey(),
            'gross_cents' => $grossAmount,
            'rate_bps' => $rateBps,
            'commission_cents' => $commissionAmount,
            'status' => CommissionStatus::Pending,
        ]);

        return [
            'partner' => $context['partner'],
            'user' => $context['user'],
            'commission' => $commission,
        ];
    }

    /**
     * @return array{
     *     partner: Partner,
     *     user: User,
     *     referred_user: User,
     *     attribution: PartnerAttribution
     * }
     */
    private function createAttribution(int $rateBps = 1000): array
    {
        $partnerUser = User::factory()->create();
        $partnerUser->assignRole(Role::Partner->value);
        $partner = Partner::query()->create([
            'user_id' => $partnerUser->getKey(),
            'display_name' => 'CTV kiểm thử',
            'default_commission_rate_bps' => $rateBps,
            'status' => PartnerStatus::Active,
        ]);
        $inviteCode = PartnerInviteCode::query()->create([
            'partner_id' => $partner->getKey(),
            'code' => 'MONEYTEST',
            'is_active' => true,
        ]);
        $referredUser = User::factory()->create();
        $attribution = PartnerAttribution::query()->create([
            'partner_id' => $partner->getKey(),
            'invite_code_id' => $inviteCode->getKey(),
            'referred_user_id' => $referredUser->getKey(),
            'attributed_at' => now(),
            'source' => AttributionSource::Link,
        ]);

        return [
            'partner' => $partner,
            'user' => $partnerUser,
            'referred_user' => $referredUser,
            'attribution' => $attribution,
        ];
    }

    private function createPayment(int $amount): Payment
    {
        return Payment::query()->create([
            'amount_cents' => $amount,
            'currency' => 'VND',
            'method' => 'fake',
            'status' => 'succeeded',
            'provider' => 'fake',
            'provider_payment_id' => 'partner-money-test',
            'paid_at' => now(),
        ]);
    }
}
