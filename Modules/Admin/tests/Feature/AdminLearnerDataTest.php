<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Database\Seeders\AuthDatabaseSeeder;
use Modules\Auth\Models\Country;
use Modules\Auth\Models\EducationStage;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\LearnerProfile;
use Modules\Auth\Models\Profession;
use Tests\TestCase;

final class AdminLearnerDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, AuthDatabaseSeeder::class]);
    }

    public function test_admin_can_view_demographics_and_manage_institutions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $country = Country::query()->where('code', 'VN')->firstOrFail();
        $existing = Institution::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.learner-data.demographics'))
            ->assertOk()
            ->assertSee('Tổng hợp dữ liệu học viên');

        $this->actingAs($admin)
            ->get(route('admin.institutions.index'))
            ->assertOk()
            ->assertSee($existing->name);

        $this->actingAs($admin)
            ->post(route('admin.institutions.store'), [
                'country_id' => $country->id,
                'administrative_unit_id' => $existing->administrative_unit_id,
                'name' => 'Trường Y khoa Kiểm thử',
                'short_name' => 'TEST-MED',
                'type' => 'university',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('institutions', [
            'name' => 'Trường Y khoa Kiểm thử',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_manage_all_learner_catalogs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        foreach ([
            'admin.countries.index' => 'Quản lý Quốc gia',
            'admin.administrative-units.index' => 'Quản lý Tỉnh/Thành phố',
            'admin.professions.index' => 'Quản lý Chức danh',
            'admin.education-stages.index' => 'Quản lý Năm học',
        ] as $route => $heading) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertSee($heading);
        }

        $this->actingAs($admin)->post(route('admin.countries.store'), [
            'code' => 'us', 'name' => 'Hoa Kỳ', 'sort_order' => 20, 'is_active' => '1',
        ])->assertRedirect(route('admin.countries.index'));
        $country = Country::query()->where('code', 'US')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.administrative-units.store'), [
            'country_id' => $country->id, 'code' => 'ca', 'name' => 'California',
            'type' => 'province', 'sort_order' => 1, 'is_active' => '1',
        ])->assertRedirect(route('admin.administrative-units.index'));

        $this->actingAs($admin)->post(route('admin.professions.store'), [
            'code' => 'dentist', 'name' => 'Nha sĩ', 'sort_order' => 10,
            'defaults_to_graduated' => '1', 'is_active' => '1',
        ])->assertRedirect(route('admin.professions.index'));

        $this->actingAs($admin)->post(route('admin.education-stages.store'), [
            'code' => 'resident', 'name' => 'Bác sĩ nội trú', 'sort_order' => 10, 'is_active' => '1',
        ])->assertRedirect(route('admin.education-stages.index'));

        $profession = Profession::query()->where('code', 'dentist')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.professions.toggle', ['item' => $profession->id]))->assertRedirect();

        $this->assertDatabaseHas('administrative_units', ['country_id' => $country->id, 'code' => 'ca']);
        $this->assertDatabaseHas('professions', ['id' => $profession->id, 'is_active' => false]);
        $this->assertDatabaseHas('education_stages', ['code' => 'resident']);
    }

    public function test_demographics_can_be_filtered_by_profile_catalog_and_marketing_consent(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $learner = User::factory()->create();
        $learner->forceFill(['last_login_method' => 'google', 'last_login_at' => now()])->save();
        $country = Country::query()->where('code', 'VN')->firstOrFail();
        $institution = Institution::query()->firstOrFail();
        $profession = Profession::query()->where('code', 'medical_student')->firstOrFail();
        $stage = EducationStage::query()->where('code', 'year_1')->firstOrFail();

        LearnerProfile::query()->create([
            'user_id' => $learner->id,
            'country_id' => $country->id,
            'administrative_unit_id' => $institution->administrative_unit_id,
            'institution_id' => $institution->id,
            'profession_id' => $profession->id,
            'education_stage_id' => $stage->id,
            'registration_method' => 'facebook',
            'onboarding_completed_at' => now(),
            'marketing_consent_at' => now(),
            'utm_source' => 'facebook',
        ]);

        $this->actingAs($admin)->get(route('admin.learner-data.demographics', [
            'institution_id' => $institution->id,
            'profession_id' => $profession->id,
            'education_stage_id' => $stage->id,
            'marketing' => 'yes',
            'utm_source' => 'facebook',
        ]))->assertOk()
            ->assertSee('Theo phương thức đăng ký')
            ->assertSee('Theo phương thức đăng nhập gần nhất')
            ->assertSee($institution->name.' — '.$institution->administrativeUnit()->value('name'))
            ->assertSee('Facebook')
            ->assertSee('facebook')
            ->assertSee('Đồng ý');

        $this->actingAs($admin)->get(route('admin.learner-data.demographics', [
            'registration_method' => 'facebook',
        ]))->assertOk()->assertSee('Facebook');

        $this->actingAs($admin)->get(route('admin.learner-data.demographics', [
            'last_login_method' => 'google',
        ]))->assertOk()->assertSee('Google');
    }
}
