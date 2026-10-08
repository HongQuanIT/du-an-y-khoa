<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\QuestionBank\Enums\TaxonomyStatus;

final class MedicalLicensingExamBlueprintSeeder extends Seeder
{
    private const DEFAULT_TOTAL_QUESTIONS = 200;

    public function run(): void
    {
        $existing = DB::table('blueprints')
            ->whereIn('code', [MedicalLicensingExamBlueprint::CODE, MedicalLicensingExamBlueprint::LEGACY_CODE])
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [MedicalLicensingExamBlueprint::CODE])
            ->first();

        if ($existing !== null) {
            $blueprintId = (int) $existing->id;
            if ((string) $existing->code !== MedicalLicensingExamBlueprint::CODE) {
                DB::table('blueprints')->where('id', $blueprintId)->update([
                    'code' => MedicalLicensingExamBlueprint::CODE,
                    'name' => MedicalLicensingExamBlueprint::NAME,
                    'updated_at' => now(),
                ]);
            }
        } else {
            $blueprintId = (int) DB::table('blueprints')->insertGetId([
                'name' => MedicalLicensingExamBlueprint::NAME,
                'slug' => Str::slug(MedicalLicensingExamBlueprint::NAME),
                'code' => MedicalLicensingExamBlueprint::CODE,
                'description' => 'Exam blueprint — 17 sections, 128 core clinical topics.',
                'status' => TaxonomyStatus::Active->value,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->ensureLicensingExamCatalog($blueprintId);

        foreach (MedicalLicensingExamBlueprint::sections() as $section) {
            $exists = DB::table('blueprint_sections')
                ->where('blueprint_id', $blueprintId)
                ->where('slug', $section['slug'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('blueprint_sections')->insert([
                'blueprint_id' => $blueprintId,
                'name' => $section['name'],
                'slug' => $section['slug'],
                'code' => $section['slug'],
                'description' => null,
                'status' => TaxonomyStatus::Active->value,
                'sort_order' => $section['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $topicsFile = __DIR__.'/data/blueprint/core_clinical_topics.php';
        if (! is_file($topicsFile)) {
            return;
        }

        /** @var array<string, list<array{name: string, slug: string, sort_order: int}>> $topicsBySection */
        $topicsBySection = require $topicsFile;

        foreach ($topicsBySection as $sectionSlug => $topics) {
            $sectionId = DB::table('blueprint_sections')
                ->where('blueprint_id', $blueprintId)
                ->where('slug', $sectionSlug)
                ->value('id');

            if ($sectionId === null) {
                continue;
            }

            foreach ($topics as $topic) {
                $exists = DB::table('core_clinical_topics')
                    ->where('blueprint_section_id', $sectionId)
                    ->where('slug', $topic['slug'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('core_clinical_topics')->insert([
                    'blueprint_section_id' => $sectionId,
                    'name' => $topic['name'],
                    'slug' => $topic['slug'],
                    'code' => $topic['slug'],
                    'description' => null,
                    'status' => TaxonomyStatus::Active->value,
                    'sort_order' => $topic['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->seedDefaultWeights($blueprintId);
    }

    /**
     * Bổ sung tỷ trọng mặc định cho ma trận chưa được cấu hình.
     *
     * Tỷ trọng phần dựa trên số chủ đề của phần đó; các chủ đề trong cùng
     * một phần được chia đều. Seeder chỉ điền giá trị còn thiếu để không ghi
     * đè cấu hình mà quản trị viên đã điều chỉnh trên local hoặc production.
     */
    private function seedDefaultWeights(int $blueprintId): void
    {
        DB::table('blueprints')
            ->where('id', $blueprintId)
            ->whereNull('total_questions')
            ->update([
                'total_questions' => self::DEFAULT_TOTAL_QUESTIONS,
                'updated_at' => now(),
            ]);

        $sections = DB::table('blueprint_sections')
            ->where('blueprint_id', $blueprintId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'weight_min', 'weight_max']);

        if ($sections->isEmpty()) {
            return;
        }

        $topicCounts = $sections
            ->mapWithKeys(fn (object $section): array => [
                (int) $section->id => DB::table('core_clinical_topics')
                    ->where('blueprint_section_id', $section->id)
                    ->count(),
            ]);
        $sectionWeights = $this->percentages($topicCounts->values()->all());

        foreach ($sections->values() as $index => $section) {
            $updates = [];
            if ($section->weight_min === null) {
                $updates['weight_min'] = $sectionWeights[$index];
            }
            if ($section->weight_max === null) {
                $updates['weight_max'] = $sectionWeights[$index];
            }
            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('blueprint_sections')->where('id', $section->id)->update($updates);
            }

            $topics = DB::table('core_clinical_topics')
                ->where('blueprint_section_id', $section->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'weight']);
            $topicWeights = $this->percentages(array_fill(0, $topics->count(), 1));

            foreach ($topics->values() as $topicIndex => $topic) {
                if ($topic->weight !== null) {
                    continue;
                }

                DB::table('core_clinical_topics')->where('id', $topic->id)->update([
                    'weight' => $topicWeights[$topicIndex],
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Chuyển các trọng số tương đối thành phần trăm với tổng chính xác 100%.
     *
     * @param  list<int>  $weights
     * @return list<float>
     */
    private function percentages(array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        $total = array_sum($weights);
        if ($total <= 0) {
            $weights = array_fill(0, count($weights), 1);
            $total = count($weights);
        }

        $basisPoints = [];
        $remainders = [];
        foreach ($weights as $index => $weight) {
            $exact = 10000 * ($weight / $total);
            $basisPoints[$index] = (int) floor($exact);
            $remainders[$index] = $exact - $basisPoints[$index];
        }

        arsort($remainders);
        $remaining = 10000 - array_sum($basisPoints);
        foreach (array_keys($remainders) as $index) {
            if ($remaining-- <= 0) {
                break;
            }
            $basisPoints[$index]++;
        }

        ksort($basisPoints);

        return array_map(fn (int $value): float => $value / 100, array_values($basisPoints));
    }

    private function ensureLicensingExamCatalog(int $blueprintId): void
    {
        if (! DB::getSchemaBuilder()->hasTable('exam_catalogs')) {
            return;
        }

        if (DB::table('exam_catalogs')->where('blueprint_id', $blueprintId)->exists()) {
            return;
        }

        $slug = Str::slug(MedicalLicensingExamBlueprint::NAME);
        if (DB::table('exam_catalogs')->where('slug', $slug)->exists()) {
            $slug .= '-'.$blueprintId;
        }

        DB::table('exam_catalogs')->insert([
            'name' => MedicalLicensingExamBlueprint::NAME,
            'slug' => $slug,
            'code' => MedicalLicensingExamBlueprint::CODE,
            'description' => 'Kỳ thi gắn ma trận cấp phép hành nghề.',
            'blueprint_id' => $blueprintId,
            'status' => TaxonomyStatus::Active->value,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
