<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class LearnerProfileCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('countries')->updateOrInsert(
            ['code' => 'VN'],
            ['name' => 'Việt Nam', 'is_active' => true, 'sort_order' => 1, 'updated_at' => $now, 'created_at' => $now],
        );

        $countryId = (int) DB::table('countries')->where('code', 'VN')->value('id');

        $units = [
            ['01', 'Hà Nội', 'city'], ['04', 'Cao Bằng', 'province'], ['08', 'Tuyên Quang', 'province'],
            ['11', 'Điện Biên', 'province'], ['12', 'Lai Châu', 'province'], ['14', 'Sơn La', 'province'],
            ['15', 'Lào Cai', 'province'], ['19', 'Thái Nguyên', 'province'], ['20', 'Lạng Sơn', 'province'],
            ['22', 'Quảng Ninh', 'province'], ['24', 'Bắc Ninh', 'province'], ['25', 'Phú Thọ', 'province'],
            ['31', 'Hải Phòng', 'city'], ['33', 'Hưng Yên', 'province'], ['37', 'Ninh Bình', 'province'],
            ['38', 'Thanh Hóa', 'province'], ['40', 'Nghệ An', 'province'], ['42', 'Hà Tĩnh', 'province'],
            ['44', 'Quảng Trị', 'province'], ['46', 'Huế', 'city'], ['48', 'Đà Nẵng', 'city'],
            ['51', 'Quảng Ngãi', 'province'], ['52', 'Gia Lai', 'province'], ['56', 'Khánh Hòa', 'province'],
            ['66', 'Đắk Lắk', 'province'], ['68', 'Lâm Đồng', 'province'], ['75', 'Đồng Nai', 'province'],
            ['79', 'Thành phố Hồ Chí Minh', 'city'], ['80', 'Tây Ninh', 'province'], ['82', 'Đồng Tháp', 'province'],
            ['86', 'Vĩnh Long', 'province'], ['91', 'An Giang', 'province'], ['92', 'Cần Thơ', 'city'],
            ['96', 'Cà Mau', 'province'],
        ];

        foreach ($units as $index => [$code, $name, $type]) {
            DB::table('administrative_units')->updateOrInsert(
                ['country_id' => $countryId, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $professions = [
            ['medical_student', 'Sinh viên y', true, false],
            ['doctor', 'Bác sĩ', false, true],
            ['nurse', 'Điều dưỡng', true, false],
            ['pharmacist', 'Dược sĩ', true, false],
            ['technician', 'Kỹ thuật viên y tế', true, false],
            ['other_healthcare', 'Nhân viên y tế khác', false, false],
        ];

        foreach ($professions as $index => [$code, $name, $requiresStage, $graduated]) {
            DB::table('professions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'requires_education_stage' => $requiresStage,
                    'defaults_to_graduated' => $graduated,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $stages = [
            ['year_1', 'Năm 1', false], ['year_2', 'Năm 2', false],
            ['year_3', 'Năm 3', false], ['year_4', 'Năm 4', false],
            ['year_5', 'Năm 5', false], ['year_6', 'Năm 6', false],
            ['graduated', 'Đã tốt nghiệp', true],
        ];

        foreach ($stages as $index => [$code, $name, $graduated]) {
            DB::table('education_stages')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'is_graduated' => $graduated,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $schools = [
            ['Thành phố Hồ Chí Minh', 'Đại học Y Dược Thành phố Hồ Chí Minh', 'UMP', 'Đại học Y Dược TP.HCM, Y Dược Sài Gòn'],
            ['Thành phố Hồ Chí Minh', 'Trường Đại học Y khoa Phạm Ngọc Thạch', 'PNTU', 'Đại học Phạm Ngọc Thạch'],
            ['Hà Nội', 'Trường Đại học Y Hà Nội', 'HMU', 'Đại học Y Hà Nội'],
            ['Hà Nội', 'Học viện Quân y', 'VMMU', 'Đại học Quân y'],
            ['Hải Phòng', 'Trường Đại học Y Dược Hải Phòng', 'HPMU', 'Y Dược Hải Phòng'],
            ['Huế', 'Trường Đại học Y - Dược, Đại học Huế', 'HUMP', 'Đại học Y Dược Huế'],
            ['Đà Nẵng', 'Khoa Y Dược, Đại học Đà Nẵng', 'SMP', 'Y Dược Đà Nẵng'],
            ['Cần Thơ', 'Trường Đại học Y Dược Cần Thơ', 'CTUMP', 'Y Dược Cần Thơ'],
            ['Thái Nguyên', 'Trường Đại học Y - Dược, Đại học Thái Nguyên', 'TUMP', 'Y Dược Thái Nguyên'],
            ['Nghệ An', 'Trường Đại học Y khoa Vinh', 'VMU', 'Đại học Y khoa Vinh'],
        ];

        foreach ($schools as $index => [$unitName, $name, $shortName, $aliases]) {
            $unitId = DB::table('administrative_units')
                ->where('country_id', $countryId)
                ->where('name', $unitName)
                ->value('id');

            DB::table('institutions')->updateOrInsert(
                ['country_id' => $countryId, 'name' => $name],
                [
                    'administrative_unit_id' => $unitId,
                    'short_name' => $shortName,
                    'search_aliases' => $aliases,
                    'type' => 'university',
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }
}
