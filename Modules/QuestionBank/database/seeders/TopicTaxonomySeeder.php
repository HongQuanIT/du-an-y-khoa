<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\QuestionBank\Models\Topic;

/**
 * Amboss-style topic taxonomy: specialties (Chuyên khoa) + organ systems
 * (Hệ cơ quan). Idempotent via slug.
 */
final class TopicTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = $this->seedSpecialties();
        $this->seedOrganSystems($specialties);
    }

    /**
     * @return array<string, Topic>
     */
    private function seedSpecialties(): array
    {
        $items = [
            'noi-khoa' => 'Nội khoa',
            'ngoai-khoa' => 'Ngoại khoa',
            'nhi-khoa' => 'Nhi khoa',
            'san-phu-khoa' => 'Sản phụ khoa',
            'duoc-ly' => 'Dược lý',
            'tam-than' => 'Tâm thần',
            'da-lieu' => 'Da liễu',
            'than-kinh' => 'Thần kinh',
            'chan-doan-hinh-anh' => 'Chẩn đoán hình ảnh',
            'hoi-suc-cap-cuu' => 'Hồi sức cấp cứu',
        ];

        $topics = [];
        $order = 0;

        foreach ($items as $slug => $name) {
            $topics[$slug] = Topic::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'type' => 'specialty',
                    'order' => $order++,
                    'parent_id' => null,
                ],
            );
        }

        return $topics;
    }

    /**
     * @param  array<string, Topic>  $specialties
     */
    private function seedOrganSystems(array $specialties): void
    {
        // Amboss USMLE-style systems + Vietnamese clinical systems used by demo questions.
        $systems = [
            // Amboss catalog
            'behavioral-health' => ['Behavioral Health', 'tam-than'],
            'biostatistics-epidemiology' => ['Biostatistics & Epidemiology', 'noi-khoa'],
            'blood-lymphoreticular' => ['Blood & Lymphoreticular Systems', 'noi-khoa'],
            'cardiovascular-system' => ['Cardiovascular System', 'noi-khoa'],
            'endocrine-system' => ['Endocrine System', 'noi-khoa'],
            'female-reproductive' => ['Female Reproductive System & Breast', 'san-phu-khoa'],
            'gastrointestinal-system' => ['Gastrointestinal System', 'noi-khoa'],
            'human-development' => ['Human Development', 'nhi-khoa'],
            'immune-system' => ['Immune System', 'noi-khoa'],
            'multisystem' => ['Multisystem Processes & Disorders', 'noi-khoa'],
            'musculoskeletal-system' => ['Musculoskeletal System', 'ngoai-khoa'],
            'nervous-system' => ['Nervous System & Special Senses', 'than-kinh'],
            'pregnancy-childbirth' => ['Pregnancy, Childbirth & the Puerperium', 'san-phu-khoa'],
            'renal-urinary' => ['Renal & Urinary System', 'noi-khoa'],
            'respiratory-system' => ['Respiratory System', 'noi-khoa'],
            'skin-subcutaneous' => ['Skin & Subcutaneous Tissue', 'da-lieu'],
            'male-reproductive' => ['Male Reproductive System', 'ngoai-khoa'],
            // Vietnamese clinical systems (demo / study plan)
            'tim-mach' => ['Tim mạch', 'noi-khoa'],
            'ho-hap' => ['Hô hấp', 'noi-khoa'],
            'noi-tiet' => ['Nội tiết', 'noi-khoa'],
            'tieu-hoa' => ['Tiêu hóa', 'ngoai-khoa'],
            'chan-thuong' => ['Chấn thương', 'ngoai-khoa'],
            'so-sinh' => ['Sơ sinh', 'nhi-khoa'],
            'khang-sinh' => ['Kháng sinh', 'duoc-ly'],
        ];

        $order = 0;

        foreach ($systems as $slug => [$name, $specialtySlug]) {
            $parent = $specialties[$specialtySlug] ?? null;

            Topic::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'type' => 'system',
                    'order' => $order++,
                    'parent_id' => $parent?->id,
                ],
            );
        }
    }
}
