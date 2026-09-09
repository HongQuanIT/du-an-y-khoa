<?php

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;

class QuestionBankDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            MedicalLicensingExamBlueprintSeeder::class,
            MedicalKnowledgeTaxonomySeeder::class,
            QuestionDemoSeeder::class,
            DemoLearningSeeder::class,
            VolumeLearningSeeder::class, // no-op unless SEED_VOLUME=true
        ]);
    }
}
