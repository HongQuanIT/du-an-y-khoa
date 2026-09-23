<?php

declare(strict_types=1);

namespace Modules\Partner\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Partner\Enums\AttributionSource;
use Modules\Partner\Enums\PartnerStatus;
use Modules\Partner\Models\Partner;
use Modules\Partner\Models\PartnerAttribution;
use Modules\Partner\Models\PartnerInviteCode;
use Tests\TestCase;

final class PartnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_shows_operational_sections_and_only_current_partner_data(): void
    {
        [$viewer, $partner] = $this->createPartner('CTV chính');
        [, $otherPartner] = $this->createPartner('CTV khác');

        $ownCode = PartnerInviteCode::query()->create([
            'partner_id' => $partner->getKey(),
            'code' => 'OWNCODE',
            'expires_at' => now()->addDays(3),
            'is_active' => true,
        ]);
        $otherCode = PartnerInviteCode::query()->create([
            'partner_id' => $otherPartner->getKey(),
            'code' => 'OTHERCODE',
            'is_active' => true,
        ]);

        $ownReferral = User::factory()->create(['name' => 'Học viên của tôi']);
        $otherReferral = User::factory()->create(['name' => 'Học viên CTV khác']);
        PartnerAttribution::query()->create([
            'partner_id' => $partner->getKey(),
            'invite_code_id' => $ownCode->getKey(),
            'referred_user_id' => $ownReferral->getKey(),
            'attributed_at' => now(),
            'source' => AttributionSource::Link,
        ]);
        PartnerAttribution::query()->create([
            'partner_id' => $otherPartner->getKey(),
            'invite_code_id' => $otherCode->getKey(),
            'referred_user_id' => $otherReferral->getKey(),
            'attributed_at' => now(),
            'source' => AttributionSource::Link,
        ]);

        $this->actingAsWithWebSession($viewer)
            ->get(route('partner.dashboard'))
            ->assertOk()
            ->assertSee('Bảng điều khiển cộng tác viên')
            ->assertSee('Hiệu quả giới thiệu')
            ->assertSee('Hoa hồng phát sinh')
            ->assertSee('<h2 id="recent-activity-heading"', false)
            ->assertSee('<time datetime=', false)
            ->assertSee('dashboard-charts.js')
            ->assertSee('OWNCODE')
            ->assertSee('Học viên của tôi')
            ->assertSee('1 mã sắp hết hạn')
            ->assertDontSee('OTHERCODE')
            ->assertDontSee('Học viên CTV khác');
    }

    /** @return array{User, Partner} */
    private function createPartner(string $name): array
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole(Role::Partner->value);
        $partner = Partner::query()->create([
            'user_id' => $user->getKey(),
            'display_name' => $name,
            'default_commission_rate_bps' => 1000,
            'status' => PartnerStatus::Active,
        ]);

        return [$user, $partner];
    }
}
