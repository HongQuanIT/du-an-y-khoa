<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Quốc gia Việt Nam, 34 tỉnh/thành (sau sáp nhập 2025) và trường y/dược trên toàn quốc.
 * Idempotent: keyed theo country code, unit code, institution name.
 */
final class VietnamGeographySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $countryId = $this->seedCountry($now);
        $this->seedAdministrativeUnits($countryId, $now);
        $this->seedInstitutions($countryId, $now);

        $this->command?->info(sprintf(
            'Địa lý VN: 1 quốc gia, %d tỉnh/thành, %d trường.',
            count($this->administrativeUnits()),
            count($this->institutions()),
        ));
    }

    private function seedCountry(object $now): int
    {
        DB::table('countries')->updateOrInsert(
            ['code' => 'VN'],
            [
                'name' => 'Việt Nam',
                'is_active' => true,
                'sort_order' => 1,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        return (int) DB::table('countries')->where('code', 'VN')->value('id');
    }

    private function seedAdministrativeUnits(int $countryId, object $now): void
    {
        foreach ($this->administrativeUnits() as $index => [$code, $name, $type]) {
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
    }

    private function seedInstitutions(int $countryId, object $now): void
    {
        foreach ($this->institutions() as $index => [$unitName, $name, $shortName, $aliases]) {
            $unitId = (int) DB::table('administrative_units')
                ->where('country_id', $countryId)
                ->where('name', $unitName)
                ->value('id');

            if ($unitId === 0) {
                throw new RuntimeException("Không tìm thấy tỉnh/thành «{$unitName}» để gán trường «{$name}».");
            }

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

    /**
     * 34 đơn vị hành chính cấp tỉnh (sau sáp nhập 2025).
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function administrativeUnits(): array
    {
        return [
            ['01', 'Hà Nội', 'city'],
            ['04', 'Cao Bằng', 'province'],
            ['08', 'Tuyên Quang', 'province'],
            ['11', 'Điện Biên', 'province'],
            ['12', 'Lai Châu', 'province'],
            ['14', 'Sơn La', 'province'],
            ['15', 'Lào Cai', 'province'],
            ['19', 'Thái Nguyên', 'province'],
            ['20', 'Lạng Sơn', 'province'],
            ['22', 'Quảng Ninh', 'province'],
            ['24', 'Bắc Ninh', 'province'],
            ['25', 'Phú Thọ', 'province'],
            ['31', 'Hải Phòng', 'city'],
            ['33', 'Hưng Yên', 'province'],
            ['37', 'Ninh Bình', 'province'],
            ['38', 'Thanh Hóa', 'province'],
            ['40', 'Nghệ An', 'province'],
            ['42', 'Hà Tĩnh', 'province'],
            ['44', 'Quảng Trị', 'province'],
            ['46', 'Huế', 'city'],
            ['48', 'Đà Nẵng', 'city'],
            ['51', 'Quảng Ngãi', 'province'],
            ['52', 'Gia Lai', 'province'],
            ['56', 'Khánh Hòa', 'province'],
            ['66', 'Đắk Lắk', 'province'],
            ['68', 'Lâm Đồng', 'province'],
            ['75', 'Đồng Nai', 'province'],
            ['79', 'Thành phố Hồ Chí Minh', 'city'],
            ['80', 'Tây Ninh', 'province'],
            ['82', 'Đồng Tháp', 'province'],
            ['86', 'Vĩnh Long', 'province'],
            ['91', 'An Giang', 'province'],
            ['92', 'Cần Thơ', 'city'],
            ['96', 'Cà Mau', 'province'],
        ];
    }

    /**
     * Trường y / dược / điều dưỡng trên các tỉnh thành Việt Nam.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function institutions(): array
    {
        return [
            ['Hà Nội', 'Trường Đại học Y Hà Nội', 'HMU', 'Đại học Y Hà Nội, Y Hà Nội'],
            ['Hà Nội', 'Học viện Quân y', 'VMMU', 'Đại học Quân y, HVQY'],
            ['Hà Nội', 'Học viện Y Dược học cổ truyền Việt Nam', 'VATM', 'Y học cổ truyền, YHCT'],
            ['Hà Nội', 'Trường Đại học Y tế Công cộng', 'HUPH', 'Y tế công cộng'],
            ['Hà Nội', 'Khoa Y Dược, Đại học Quốc gia Hà Nội', 'VNU-UMP', 'Y Dược ĐHQGHN'],
            ['Hà Nội', 'VinUniversity — College of Health Sciences', 'VinUni', 'VinUniversity, VinUni'],
            ['Hà Nội', 'Trường Đại học Phenikaa (Khoa Y)', 'Phenikaa', 'Phenikaa University'],
            ['Hà Nội', 'Trường Đại học Kinh doanh và Công nghệ Hà Nội (Khoa Y Dược)', 'HUBT', 'Đại học Kinh doanh và Công nghệ'],
            ['Hải Phòng', 'Trường Đại học Y Dược Hải Phòng', 'HPMU', 'Y Dược Hải Phòng'],
            ['Hải Phòng', 'Trường Đại học Kỹ thuật Y tế Hải Dương', 'HMTU', 'Kỹ thuật Y tế Hải Dương'],
            ['Ninh Bình', 'Trường Đại học Điều dưỡng Nam Định', 'NDUN', 'Điều dưỡng Nam Định'],
            ['Hưng Yên', 'Trường Đại học Y khoa Tokyo Việt Nam', 'TMUV', 'Tokyo Medical University Vietnam, Y khoa Tokyo'],
            ['Thanh Hóa', 'Trường Đại học Hồng Đức (Khoa Y)', 'HDU', 'Hồng Đức Thanh Hóa'],
            ['Nghệ An', 'Trường Đại học Y khoa Vinh', 'VMU', 'Đại học Y khoa Vinh, Y Vinh'],
            ['Huế', 'Trường Đại học Y - Dược, Đại học Huế', 'HUMP', 'Đại học Y Dược Huế, Y Dược Huế'],
            ['Đà Nẵng', 'Khoa Y Dược, Đại học Đà Nẵng', 'UD-SMP', 'Y Dược Đà Nẵng'],
            ['Đà Nẵng', 'Trường Đại học Kỹ thuật Y Dược Đà Nẵng', 'DUMTP', 'Kỹ thuật Y Dược Đà Nẵng'],
            ['Đà Nẵng', 'Trường Đại học Duy Tân (Khoa Y)', 'DTU', 'Duy Tân'],
            ['Đà Nẵng', 'Trường Đại học Phan Châu Trinh (Khoa Y)', 'PCTU', 'Phan Châu Trinh'],
            ['Đà Nẵng', 'Trường Đại học Đông Á (Khoa Y)', 'DAU', 'Đông Á'],
            ['Khánh Hòa', 'Trường Đại học Nha Trang (Khoa Y)', 'NTU', 'Đại học Nha Trang'],
            ['Thành phố Hồ Chí Minh', 'Đại học Y Dược Thành phố Hồ Chí Minh', 'UMP', 'Đại học Y Dược TP.HCM, Y Dược Sài Gòn'],
            ['Thành phố Hồ Chí Minh', 'Trường Đại học Y khoa Phạm Ngọc Thạch', 'PNTU', 'Đại học Phạm Ngọc Thạch, Y khoa PNT'],
            ['Thành phố Hồ Chí Minh', 'Khoa Y, Đại học Quốc gia Thành phố Hồ Chí Minh', 'VNUHCM-SMP', 'Y ĐHQG TP.HCM'],
            ['Thành phố Hồ Chí Minh', 'Trường Đại học Văn Lang (Khoa Y)', 'VLU', 'Văn Lang'],
            ['Thành phố Hồ Chí Minh', 'Trường Đại học Nguyễn Tất Thành (Khoa Y)', 'NTTU', 'Nguyễn Tất Thành'],
            ['Thành phố Hồ Chí Minh', 'Trường Đại học Quốc tế Hồng Bàng (Khoa Y)', 'HIU', 'Hồng Bàng'],
            ['Tây Ninh', 'Trường Đại học Tân Tạo (Khoa Y)', 'TTU', 'Tân Tạo'],
            ['Đồng Nai', 'Trường Đại học Lạc Hồng (Khoa Y)', 'LHU', 'Lạc Hồng'],
            ['Cần Thơ', 'Trường Đại học Y Dược Cần Thơ', 'CTUMP', 'Y Dược Cần Thơ'],
            ['Cần Thơ', 'Trường Đại học Nam Cần Thơ (Khoa Y)', 'NCTU', 'Nam Cần Thơ'],
            ['Cần Thơ', 'Trường Đại học Võ Trường Toản (Khoa Y)', 'VTTU', 'Võ Trường Toản'],
            ['Thái Nguyên', 'Trường Đại học Y - Dược, Đại học Thái Nguyên', 'TUMP', 'Y Dược Thái Nguyên'],
            ['Đắk Lắk', 'Khoa Y Dược, Trường Đại học Tây Nguyên', 'TNU-Med', 'Y Dược Tây Nguyên'],
            ['Đắk Lắk', 'Trường Đại học Buôn Ma Thuột (Khoa Y)', 'BMTU', 'Buôn Ma Thuột'],
            ['Vĩnh Long', 'Trường Đại học Trà Vinh (Khoa Y Dược)', 'TVU', 'Y Dược Trà Vinh'],
            ['An Giang', 'Trường Đại học An Giang (Khoa Y)', 'AGU', 'Đại học An Giang'],
        ];
    }
}
