<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\QuestionBank\Enums\TaxonomyStatus;

final class MedicalLicensingExamBlueprintSeeder extends Seeder
{
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
    }
}
