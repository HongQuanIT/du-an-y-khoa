<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Baseline learner-profile catalogs for local / staging / production.
 * Địa lý (quốc gia / tỉnh / trường) → {@see VietnamGeographySeeder}.
 * Idempotent: keyed on profession/stage code.
 */
final class LearnerProfileCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(VietnamGeographySeeder::class);

        $now = now();
        $this->seedProfessions($now);
        $this->seedEducationStages($now);

        $this->command?->info('Learner catalog: địa lý VN + chức danh + năm học.');
    }

    private function seedProfessions(object $now): void
    {
        // Remove legacy entries that are no longer offered in the learner profile.
        DB::table('professions')->whereIn('code', [
            'doctor',
            'medical_student',
            'intern',
            'resident',
            'pharmacist',
            'technician',
            'other_healthcare',
        ])->delete();

        foreach ($this->professions() as $index => [$code, $name, $requiresStage, $graduated]) {
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
    }

    private function seedEducationStages(object $now): void
    {
        foreach ($this->educationStages() as $index => [$code, $name, $graduated]) {
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
    }

    /**
     * @return list<array{0: string, 1: string, 2: bool, 3: bool}>
     */
    private function professions(): array
    {
        return [
            ['nurse', 'Điều dưỡng', true, false],
            ['medical_doctor', 'Bác sĩ y khoa', true, false],
            ['dentist', 'Bác sĩ răng Hàm Mặt', true, false],
            ['preventive_medicine_doctor', 'Bác sĩ y học dự phòng', true, false],
            ['traditional_medicine_doctor', 'Bác sĩ y học cổ truyền', true, false],
            ['midwife', 'Hộ sinh', true, false],
            ['medical_laboratory_technician', 'Kỹ thuật xét nghiệm y học', true, false],
            ['medical_imaging_technician', 'Kỹ thuật hình ảnh y học', true, false],
            ['rehabilitation_technician', 'Kỹ thuật phục hồi chức năng', true, false],
            ['clinical_nutritionist', 'Dinh dưỡng lâm sàng', true, false],
            ['clinical_psychologist', 'Tâm lý lâm sàng', true, false],
            ['other', 'Khác', false, false],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    private function educationStages(): array
    {
        return [
            ['year_1', 'Năm 1', false],
            ['year_2', 'Năm 2', false],
            ['year_3', 'Năm 3', false],
            ['year_4', 'Năm 4', false],
            ['year_5', 'Năm 5', false],
            ['year_6', 'Năm 6', false],
            ['graduated', 'Đã tốt nghiệp', true],
        ];
    }
}
