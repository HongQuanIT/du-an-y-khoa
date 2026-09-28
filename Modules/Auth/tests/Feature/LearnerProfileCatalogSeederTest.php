<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Database\Seeders\LearnerProfileCatalogSeeder;
use Tests\TestCase;

final class LearnerProfileCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_fills_vietnam_catalog_for_onboarding(): void
    {
        $this->seed(LearnerProfileCatalogSeeder::class);

        $this->assertDatabaseHas('countries', ['code' => 'VN', 'name' => 'Việt Nam', 'is_active' => true]);
        $this->assertDatabaseCount('administrative_units', 34);
        $this->assertDatabaseHas('administrative_units', ['code' => '01', 'name' => 'Hà Nội']);
        $this->assertDatabaseHas('administrative_units', ['code' => '79', 'name' => 'Thành phố Hồ Chí Minh']);

        $this->assertDatabaseHas('professions', ['code' => 'medical_doctor', 'name' => 'Bác sĩ y khoa']);
        $this->assertDatabaseHas('professions', ['code' => 'midwife', 'name' => 'Hộ sinh']);
        $this->assertDatabaseHas('professions', ['code' => 'clinical_psychologist', 'name' => 'Tâm lý lâm sàng']);
        $this->assertDatabaseMissing('professions', ['code' => 'doctor']);

        $this->assertDatabaseHas('education_stages', ['code' => 'year_1', 'name' => 'Năm 1']);
        $this->assertDatabaseHas('education_stages', ['code' => 'year_6', 'name' => 'Năm 6']);
        $this->assertDatabaseHas('education_stages', ['code' => 'graduated', 'name' => 'Đã tốt nghiệp']);

        $this->assertDatabaseHas('institutions', ['short_name' => 'HMU', 'name' => 'Trường Đại học Y Hà Nội']);
        $this->assertDatabaseHas('institutions', ['short_name' => 'UMP']);
        $this->assertGreaterThanOrEqual(34, DB::table('institutions')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(LearnerProfileCatalogSeeder::class);
        $this->seed(LearnerProfileCatalogSeeder::class);

        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('administrative_units', 34);
        $this->assertSame(0, DB::table('professions')->where('code', 'medical_student')->count());
        $this->assertSame(0, DB::table('professions')->where('code', 'resident')->count());
        $this->assertSame(0, DB::table('professions')->where('code', 'doctor')->count());
        $this->assertSame(1, DB::table('institutions')->where('short_name', 'HMU')->count());
    }
}
