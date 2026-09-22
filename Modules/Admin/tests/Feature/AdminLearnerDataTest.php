<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Database\Seeders\AuthDatabaseSeeder;
use Modules\Auth\Models\Country;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\AdministrativeUnit;
use Modules\Auth\Models\Profession;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

final class AdminLearnerDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, AuthDatabaseSeeder::class]);
    }

    public function test_admin_can_manage_institutions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $country = Country::query()->where('code', 'VN')->firstOrFail();
        $existing = Institution::query()->orderBy('name')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.institutions.index'))
            ->assertOk()
            ->assertSee($existing->name)
            ->assertSee('Thêm trường')
            ->assertSee('formModalOpen');

        $this->actingAs($admin)
            ->get(route('admin.institutions.index', ['edit' => $existing->id]))
            ->assertOk()
            ->assertSee('Chỉnh sửa trường/cơ sở đào tạo')
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
            $this->actingAs($admin)->get(route($route))
                ->assertOk()
                ->assertSee($heading)
                ->assertSee('formModalOpen');
        }

        $professionForEdit = Profession::query()->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.professions.index', ['edit' => $professionForEdit->id]))
            ->assertOk()
            ->assertSee('Chỉnh sửa chức danh')
            ->assertSee($professionForEdit->name);

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

        $this->assertDatabaseHas('administrative_units', ['country_id' => $country->id, 'code' => 'ca']);
        $this->assertDatabaseHas('professions', ['code' => 'dentist', 'is_active' => true]);
        $this->assertDatabaseHas('education_stages', ['code' => 'resident']);
    }

    public function test_admin_can_filter_institutions_by_multiple_catalog_values_with_ajax(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);
        $vietnam = Country::query()->where('code', 'VN')->firstOrFail();
        $unitedStates = Country::query()->create([
            'code' => 'US',
            'name' => 'Hoa Kỳ',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $vietnamUnit = AdministrativeUnit::query()->create([
            'country_id' => $vietnam->id,
            'code' => 'test-vn',
            'name' => 'Tỉnh kiểm thử Việt Nam',
            'type' => 'province',
            'is_active' => true,
        ]);
        $unitedStatesUnit = AdministrativeUnit::query()->create([
            'country_id' => $unitedStates->id,
            'code' => 'test-us',
            'name' => 'Tiểu bang kiểm thử Hoa Kỳ',
            'type' => 'state',
            'is_active' => true,
        ]);

        foreach ([[$vietnam, $vietnamUnit, 'Trường kiểm thử Việt Nam', true], [$unitedStates, $unitedStatesUnit, 'Trường kiểm thử Hoa Kỳ', false]] as [$country, $unit, $name, $active]) {
            Institution::query()->create([
                'country_id' => $country->id,
                'administrative_unit_id' => $unit->id,
                'name' => $name,
                'type' => 'university',
                'is_active' => $active,
            ]);
        }

        $this->actingAs($admin)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.institutions.index', [
                'country_id' => [$vietnam->id, $unitedStates->id],
                'administrative_unit_id' => [$vietnamUnit->id, $unitedStatesUnit->id],
                'status' => ['active', 'inactive'],
            ]))
            ->assertOk()
            ->assertSee('institution-results-region', false)
            ->assertSee('Trường kiểm thử Việt Nam')
            ->assertSee('Trường kiểm thử Hoa Kỳ');
    }

    public function test_learner_catalog_actions_follow_each_granular_permission(): void
    {
        $role = RoleModel::create([
            'name' => 'learner_catalog_editor',
            'guard_name' => 'web',
            'portal' => 'admin',
        ]);
        $role->givePermissionTo(['learner_catalog.view', 'learner_catalog.update']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $institution = Institution::query()->where('is_active', true)->firstOrFail();

        $this->actingAsWithWebSession($user)
            ->get(route('admin.institutions.index'))
            ->assertOk()
            ->assertSee('Chỉnh sửa')
            ->assertDontSee('Thêm trường');

        $this->actingAsWithWebSession($user)
            ->post(route('admin.institutions.store'), [])
            ->assertForbidden();
        $this->actingAsWithWebSession($user)
            ->put(route('admin.institutions.update', $institution), [
                'country_id' => $institution->country_id,
                'administrative_unit_id' => $institution->administrative_unit_id,
                'name' => $institution->name,
                'short_name' => $institution->short_name,
                'type' => $institution->type,
                'sort_order' => $institution->sort_order,
                'is_active' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse($institution->fresh()->is_active);
    }
}
