<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Database\Seeders\VietnamGeographySeeder;
use Tests\TestCase;

final class VietnamGeographySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_fills_vietnam_country_units_and_institutions(): void
    {
        $this->seed(VietnamGeographySeeder::class);

        $this->assertDatabaseHas('countries', ['code' => 'VN', 'name' => 'Việt Nam', 'is_active' => true]);
        $this->assertDatabaseCount('administrative_units', 34);
        $this->assertDatabaseHas('administrative_units', ['code' => '01', 'name' => 'Hà Nội', 'type' => 'city']);
        $this->assertDatabaseHas('administrative_units', ['code' => '79', 'name' => 'Thành phố Hồ Chí Minh', 'type' => 'city']);
        $this->assertDatabaseHas('administrative_units', ['code' => '96', 'name' => 'Cà Mau', 'type' => 'province']);

        $this->assertDatabaseHas('institutions', ['short_name' => 'HMU', 'name' => 'Trường Đại học Y Hà Nội']);
        $this->assertDatabaseHas('institutions', ['short_name' => 'UMP']);
        $this->assertDatabaseHas('institutions', ['short_name' => 'CTUMP']);
        $this->assertGreaterThanOrEqual(34, DB::table('institutions')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(VietnamGeographySeeder::class);
        $this->seed(VietnamGeographySeeder::class);

        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('administrative_units', 34);
        $this->assertSame(1, DB::table('institutions')->where('short_name', 'HMU')->count());
    }
}
