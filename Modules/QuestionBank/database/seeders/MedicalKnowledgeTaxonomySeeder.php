<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\QuestionBank\Enums\TaxonomyStatus;

/**
 * Idempotent medical knowledge taxonomy + STEMI demo tree + tags.
 */
final class MedicalKnowledgeTaxonomySeeder extends Seeder
{
    public const TAXONOMY_CODE = 'medlearn-medical-taxonomy';

    public const TAXONOMY_NAME = 'MedLearn Medical Knowledge Taxonomy';

    public function run(): void
    {
        $this->removeLegacyTaxonomy();
        $taxonomyId = $this->upsertTaxonomy();
        $nodes = $this->seedNodes($taxonomyId);
        $this->seedTags();
        $this->mapChestPainCoreTopic($nodes);
    }

    private function removeLegacyTaxonomy(): void
    {
        $legacyId = DB::table('medical_taxonomies')->where('code', 'medlearn')->value('id');
        if ($legacyId === null) {
            return;
        }

        $nodeIds = DB::table('medical_taxonomy_nodes')
            ->where('medical_taxonomy_id', $legacyId)
            ->pluck('id');

        if ($nodeIds->isNotEmpty()) {
            DB::table('question_medical_topics')
                ->whereIn('medical_taxonomy_node_id', $nodeIds)
                ->delete();

            if (Schema::hasTable('core_topic_medical_taxonomy_nodes')) {
                DB::table('core_topic_medical_taxonomy_nodes')
                    ->whereIn('medical_taxonomy_node_id', $nodeIds)
                    ->delete();
            }

            DB::table('medical_taxonomy_nodes')
                ->where('medical_taxonomy_id', $legacyId)
                ->delete();
        }

        DB::table('medical_taxonomies')->where('id', $legacyId)->delete();
    }

    private function upsertTaxonomy(): int
    {
        $existing = DB::table('medical_taxonomies')->where('code', self::TAXONOMY_CODE)->first();

        if ($existing !== null) {
            DB::table('medical_taxonomies')->where('id', $existing->id)->update([
                'name' => self::TAXONOMY_NAME,
                'description' => 'Independent medical knowledge taxonomy (not the exam blueprint).',
                'status' => TaxonomyStatus::Active->value,
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('medical_taxonomies')->insertGetId([
            'name' => self::TAXONOMY_NAME,
            'code' => self::TAXONOMY_CODE,
            'description' => 'Independent medical knowledge taxonomy (not the exam blueprint).',
            'status' => TaxonomyStatus::Active->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, int> slug => id
     */
    private function seedNodes(int $taxonomyId): array
    {
        $tree = [
            ['name' => 'Hệ tim mạch', 'slug' => 'he-tim-mach-system', 'node_type' => 'system', 'parent' => null],
            ['name' => 'Tim mạch', 'slug' => 'tim-mach', 'node_type' => 'specialty', 'parent' => 'he-tim-mach-system'],
            ['name' => 'Bệnh động mạch vành', 'slug' => 'benh-dong-mach-vanh', 'node_type' => 'condition', 'parent' => 'tim-mach'],
            ['name' => 'Hội chứng vành cấp', 'slug' => 'hoi-chung-vanh-cap', 'node_type' => 'condition', 'parent' => 'benh-dong-mach-vanh'],
            ['name' => 'Nhồi máu cơ tim', 'slug' => 'nhoi-mau-co-tim', 'node_type' => 'disease', 'parent' => 'hoi-chung-vanh-cap'],
            ['name' => 'STEMI', 'slug' => 'stemi', 'node_type' => 'disease', 'parent' => 'nhoi-mau-co-tim'],

            ['name' => 'Đau ngực', 'slug' => 'symptom-dau-nguc', 'node_type' => 'symptom', 'parent' => null],
            ['name' => 'Khó thở', 'slug' => 'symptom-kho-tho', 'node_type' => 'symptom', 'parent' => null],
            ['name' => 'Vã mồ hôi', 'slug' => 'symptom-va-mo-hoi', 'node_type' => 'symptom', 'parent' => null],

            ['name' => 'ST chênh lên', 'slug' => 'finding-st-chenh-len', 'node_type' => 'clinical_finding', 'parent' => null],
            ['name' => 'Troponin I tăng', 'slug' => 'finding-troponin-i-tang', 'node_type' => 'lab_finding', 'parent' => null],

            ['name' => 'Nhận diện hội chứng vành cấp', 'slug' => 'concept-nhan-dien-acs', 'node_type' => 'concept', 'parent' => null],
            ['name' => 'Nhận diện STEMI', 'slug' => 'concept-nhan-dien-stemi', 'node_type' => 'concept', 'parent' => null],
            ['name' => 'Định khu nhồi máu cơ tim trên ECG', 'slug' => 'concept-dinh-khu-mi-ecg', 'node_type' => 'concept', 'parent' => null],
            ['name' => 'Vai trò của troponin trong chẩn đoán nhồi máu cơ tim', 'slug' => 'concept-troponin-mi', 'node_type' => 'concept', 'parent' => null],
            ['name' => 'Chẩn đoán phân biệt đau ngực cấp', 'slug' => 'concept-ddx-dau-nguc-cap', 'node_type' => 'concept', 'parent' => null],
            ['name' => 'Tái tưới máu trong STEMI', 'slug' => 'concept-tai-tuoi-mau-stemi', 'node_type' => 'concept', 'parent' => null],
            ['name' => 'PCI cấp cứu', 'slug' => 'concept-pci-cap-cuu', 'node_type' => 'concept', 'parent' => null],
        ];

        $ids = [];
        $sort = 1;

        foreach ($tree as $node) {
            $parentId = $node['parent'] !== null ? ($ids[$node['parent']] ?? null) : null;
            $existingId = DB::table('medical_taxonomy_nodes')
                ->where('medical_taxonomy_id', $taxonomyId)
                ->where('slug', $node['slug'])
                ->value('id');

            if ($existingId !== null) {
                DB::table('medical_taxonomy_nodes')->where('id', $existingId)->update([
                    'parent_id' => $parentId,
                    'name' => $node['name'],
                    'code' => $node['slug'],
                    'node_type' => $node['node_type'],
                    'sort_order' => $sort,
                    'status' => TaxonomyStatus::Active->value,
                    'updated_at' => now(),
                ]);
                $ids[$node['slug']] = (int) $existingId;
            } else {
                $ids[$node['slug']] = (int) DB::table('medical_taxonomy_nodes')->insertGetId([
                    'medical_taxonomy_id' => $taxonomyId,
                    'parent_id' => $parentId,
                    'name' => $node['name'],
                    'slug' => $node['slug'],
                    'code' => $node['slug'],
                    'node_type' => $node['node_type'],
                    'description' => null,
                    'sort_order' => $sort,
                    'status' => TaxonomyStatus::Active->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $sort++;
        }

        return $ids;
    }

    private function seedTags(): void
    {
        $tags = [
            ['name' => 'ECG', 'slug' => 'ecg'],
            ['name' => 'Cardiology', 'slug' => 'cardiology'],
            ['name' => 'Emergency', 'slug' => 'emergency'],
            ['name' => 'Diagnosis', 'slug' => 'diagnosis'],
            ['name' => 'High-yield', 'slug' => 'high-yield'],
            ['name' => 'Adult', 'slug' => 'adult'],
        ];

        foreach ($tags as $tag) {
            $exists = DB::table('tags')->where('slug', $tag['slug'])->exists();
            if ($exists) {
                DB::table('tags')->where('slug', $tag['slug'])->update([
                    'name' => $tag['name'],
                    'status' => TaxonomyStatus::Active->value,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('tags')->insert([
                'name' => $tag['name'],
                'slug' => $tag['slug'],
                'type' => 'content',
                'description' => null,
                'status' => TaxonomyStatus::Active->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, int>  $nodes
     */
    private function mapChestPainCoreTopic(array $nodes): void
    {
        $coreTopicId = DB::table('core_clinical_topics')
            ->where('slug', 'dau-nguc')
            ->value('id');

        if ($coreTopicId === null) {
            return;
        }

        $linkSlugs = ['stemi', 'symptom-dau-nguc', 'finding-st-chenh-len', 'concept-nhan-dien-stemi'];
        $now = now();

        foreach ($linkSlugs as $slug) {
            $nodeId = $nodes[$slug] ?? null;
            if ($nodeId === null) {
                continue;
            }

            $exists = DB::table('core_topic_medical_taxonomy_nodes')
                ->where('core_clinical_topic_id', $coreTopicId)
                ->where('medical_taxonomy_node_id', $nodeId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('core_topic_medical_taxonomy_nodes')->insert([
                'core_clinical_topic_id' => $coreTopicId,
                'medical_taxonomy_node_id' => $nodeId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
