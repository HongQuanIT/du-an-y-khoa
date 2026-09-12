<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * Idempotent 3-level content taxonomy demo:
 *   Hệ cơ quan (organ_systems) → Môn học (subjects) → Bài học (lessons)
 * plus the orthogonal tags axis (symptom / finding / concept…).
 */
final class MedicalKnowledgeTaxonomySeeder extends Seeder
{
    /** Lessons attached to the STEMI demo question. */
    public const DEMO_LESSON_SLUGS = [
        'benh-dong-mach-vanh',
        'hoi-chung-vanh-cap',
        'nhoi-mau-co-tim',
        'stemi',
    ];

    /** Tags attached to the STEMI demo question. */
    public const DEMO_TAG_SLUGS = [
        'trieu-chung-dau-nguc',
        'trieu-chung-kho-tho',
        'trieu-chung-va-mo-hoi',
        'dau-hieu-st-chenh-len',
        'xn-troponin-i-tang',
        'kn-nhan-dien-stemi',
        'kn-dinh-khu-mi-ecg',
        'kn-troponin-mi',
        'kn-tai-tuoi-mau-stemi',
        'ecg',
        'cardiology',
        'emergency',
        'diagnosis',
        'high-yield',
        'adult',
    ];

    public function run(): void
    {
        $lessonIds = $this->seedCurriculum();
        $this->seedTags();
        $this->mapChestPainCoreTopic($lessonIds);
    }

    /**
     * @return array<string, int> lesson slug => id
     */
    private function seedCurriculum(): array
    {
        $tree = [
            [
                'slug' => 'he-tim-mach',
                'name' => 'Hệ tim mạch',
                'subjects' => [
                    [
                        'slug' => 'tim-mach',
                        'name' => 'Tim mạch',
                        'lessons' => [
                            'benh-dong-mach-vanh' => 'Bệnh động mạch vành',
                            'hoi-chung-vanh-cap' => 'Hội chứng vành cấp',
                            'nhoi-mau-co-tim' => 'Nhồi máu cơ tim',
                            'stemi' => 'STEMI',
                            'suy-tim' => 'Suy tim',
                            'roi-loan-nhip' => 'Rối loạn nhịp',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'he-ho-hap',
                'name' => 'Hệ hô hấp',
                'subjects' => [
                    [
                        'slug' => 'ho-hap',
                        'name' => 'Hô hấp',
                        'lessons' => [
                            'viem-phoi' => 'Viêm phổi',
                            'hen-phe-quan' => 'Hen phế quản',
                            'copd' => 'COPD',
                            'thuyen-tac-phoi' => 'Thuyên tắc phổi',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'he-tieu-hoa',
                'name' => 'Hệ tiêu hóa',
                'subjects' => [
                    [
                        'slug' => 'tieu-hoa',
                        'name' => 'Tiêu hóa',
                        'lessons' => [
                            'loet-da-day' => 'Loét dạ dày – tá tràng',
                            'viem-tuy-cap' => 'Viêm tụy cấp',
                            'xo-gan' => 'Xơ gan',
                            'viem-ruot-thua' => 'Viêm ruột thừa',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'he-than-kinh',
                'name' => 'Hệ thần kinh',
                'subjects' => [
                    [
                        'slug' => 'than-kinh',
                        'name' => 'Thần kinh',
                        'lessons' => [
                            'dot-quy' => 'Đột quỵ',
                            'viem-mang-nao' => 'Viêm màng não',
                            'dong-kinh' => 'Động kinh',
                            'migraine' => 'Migraine',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'he-noi-tiet',
                'name' => 'Hệ nội tiết',
                'subjects' => [
                    [
                        'slug' => 'noi-tiet',
                        'name' => 'Nội tiết',
                        'lessons' => [
                            'dai-thao-duong' => 'Đái tháo đường',
                            'cuong-giap' => 'Cường giáp',
                            'suy-thuong-than' => 'Suy thượng thận',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'he-tiet-nieu',
                'name' => 'Hệ tiết niệu',
                'subjects' => [
                    [
                        'slug' => 'than-tiet-nieu',
                        'name' => 'Thận – tiết niệu',
                        'lessons' => [
                            'suy-than-cap' => 'Suy thận cấp',
                            'viem-be-than' => 'Viêm bể thận',
                            'soi-tiet-nieu' => 'Sỏi tiết niệu',
                            'urology' => 'Tiết niệu',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'he-mau',
                'name' => 'Hệ máu',
                'subjects' => [
                    [
                        'slug' => 'huyet-hoc',
                        'name' => 'Huyết học',
                        'lessons' => [
                            'thieu-mau' => 'Thiếu máu',
                            'roi-loan-dong-mau' => 'Rối loạn đông máu',
                            'bach-cau-cap' => 'Bạch cầu cấp',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'he-co-xuong-khop',
                'name' => 'Hệ cơ xương khớp',
                'subjects' => [
                    [
                        'slug' => 'co-xuong-khop',
                        'name' => 'Cơ xương khớp',
                        'lessons' => [
                            'gout' => 'Gout',
                            'viem-khop-dang-thap' => 'Viêm khớp dạng thấp',
                            'loang-xuong' => 'Loãng xương',
                        ],
                    ],
                ],
            ],
        ];

        $ids = [];
        $organSort = 1;

        foreach ($tree as $organ) {
            $organSystemId = $this->upsert('organ_systems', $organ['slug'], $organ['name'], $organSort);
            $subjectSort = 1;

            foreach ($organ['subjects'] as $subject) {
                $subjectId = $this->upsert('subjects', $subject['slug'], $subject['name'], $subjectSort);
                $this->link('subject_organ_system', 'subject_id', $subjectId, 'organ_system_id', $organSystemId);

                $lessonSort = 1;
                foreach ($subject['lessons'] as $slug => $name) {
                    $lessonId = $this->upsert('lessons', $slug, $name, $lessonSort);
                    $this->link('lesson_subject', 'lesson_id', $lessonId, 'subject_id', $subjectId, $lessonSort);
                    $ids[$slug] = $lessonId;
                    $lessonSort++;
                }

                $subjectSort++;
            }

            $organSort++;
        }

        return $ids;
    }

    private function upsert(string $table, string $slug, string $name, int $sort): int
    {
        $existingId = DB::table($table)->where('slug', $slug)->value('id');

        if ($existingId !== null) {
            DB::table($table)->where('id', $existingId)->update([
                'name' => $name,
                'code' => $slug,
                'sort_order' => $sort,
                'status' => TaxonomyStatus::Active->value,
                'updated_at' => now(),
            ]);

            return (int) $existingId;
        }

        return (int) DB::table($table)->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'code' => $slug,
            'description' => null,
            'sort_order' => $sort,
            'status' => TaxonomyStatus::Active->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function link(string $table, string $leftKey, int $leftId, string $rightKey, int $rightId, int $sort = 0): void
    {
        $exists = DB::table($table)
            ->where($leftKey, $leftId)
            ->where($rightKey, $rightId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table($table)->insert([
            $leftKey => $leftId,
            $rightKey => $rightId,
            'sort_order' => $sort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedTags(): void
    {
        $tags = [
            ['name' => 'Đau ngực', 'slug' => 'trieu-chung-dau-nguc', 'type' => 'symptom'],
            ['name' => 'Khó thở', 'slug' => 'trieu-chung-kho-tho', 'type' => 'symptom'],
            ['name' => 'Vã mồ hôi', 'slug' => 'trieu-chung-va-mo-hoi', 'type' => 'symptom'],
            ['name' => 'ST chênh lên', 'slug' => 'dau-hieu-st-chenh-len', 'type' => 'clinical_finding'],
            ['name' => 'Troponin I tăng', 'slug' => 'xn-troponin-i-tang', 'type' => 'lab_finding'],
            ['name' => 'Nhận diện STEMI', 'slug' => 'kn-nhan-dien-stemi', 'type' => 'concept'],
            ['name' => 'Định khu nhồi máu cơ tim trên ECG', 'slug' => 'kn-dinh-khu-mi-ecg', 'type' => 'concept'],
            ['name' => 'Vai trò của troponin', 'slug' => 'kn-troponin-mi', 'type' => 'concept'],
            ['name' => 'Tái tưới máu trong STEMI', 'slug' => 'kn-tai-tuoi-mau-stemi', 'type' => 'concept'],
            ['name' => 'ECG', 'slug' => 'ecg', 'type' => 'concept'],
            ['name' => 'Cardiology', 'slug' => 'cardiology', 'type' => 'other'],
            ['name' => 'Emergency', 'slug' => 'emergency', 'type' => 'other'],
            ['name' => 'Diagnosis', 'slug' => 'diagnosis', 'type' => 'concept'],
            ['name' => 'High-yield', 'slug' => 'high-yield', 'type' => 'high_yield'],
            ['name' => 'Adult', 'slug' => 'adult', 'type' => 'other'],
        ];

        foreach ($tags as $tag) {
            if (DB::table('tags')->where('slug', $tag['slug'])->exists()) {
                DB::table('tags')->where('slug', $tag['slug'])->update([
                    'name' => $tag['name'],
                    'type' => $tag['type'],
                    'status' => TaxonomyStatus::Active->value,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('tags')->insert([
                'name' => $tag['name'],
                'slug' => $tag['slug'],
                'type' => $tag['type'],
                'description' => null,
                'status' => TaxonomyStatus::Active->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, int>  $lessonIds
     */
    private function mapChestPainCoreTopic(array $lessonIds): void
    {
        $coreTopicId = DB::table('core_clinical_topics')->where('slug', 'dau-nguc')->value('id');
        if ($coreTopicId === null) {
            return;
        }

        $now = now();
        foreach (['stemi', 'hoi-chung-vanh-cap'] as $slug) {
            $lessonId = $lessonIds[$slug] ?? null;
            if ($lessonId === null) {
                continue;
            }

            $exists = DB::table('core_topic_lessons')
                ->where('core_clinical_topic_id', $coreTopicId)
                ->where('lesson_id', $lessonId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('core_topic_lessons')->insert([
                'core_clinical_topic_id' => $coreTopicId,
                'lesson_id' => $lessonId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
