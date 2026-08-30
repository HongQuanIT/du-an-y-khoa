<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Database\Seeders\CmsPageSeeder;
use Modules\Admin\Database\Seeders\FaqSeeder;
use Modules\Admin\Database\Seeders\MenuSeeder;
use Modules\Admin\Database\Seeders\SettingsSeeder;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Partner\Database\Seeders\PartnerSettingsSeeder;
use Modules\QuestionBank\Database\Seeders\MedicalKnowledgeTaxonomySeeder;
use Modules\QuestionBank\Database\Seeders\MedicalLicensingExamBlueprintSeeder;

/**
 * Baseline for staging (near-production).
 *
 * No demo QBank/library/study-plan/banner data. Includes QA accounts + FAQ starter.
 * Does not wipe the database — see docs/seeding.md.
 *
 *   php artisan app:seed staging
 *   php artisan app:seed staging --fresh   # migrate:fresh rồi seed lại
 *   php artisan db:seed --class=StagingSeeder
 */
class StagingSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,

            MedicalLicensingExamBlueprintSeeder::class,
            MedicalKnowledgeTaxonomySeeder::class,

            BillingDatabaseSeeder::class,

            SettingsSeeder::class,
            PartnerSettingsSeeder::class,
            CmsPageSeeder::class,
            MenuSeeder::class,
            FaqSeeder::class,
        ]);

        $this->command->info('Staging seed xong. QA: superadmin@medlearn.local / student@medlearn.local (xem UserSeeder).');
    }
}
