<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Database\Seeders\CmsPageSeeder;
use Modules\Admin\Database\Seeders\MenuSeeder;
use Modules\Admin\Database\Seeders\SettingsSeeder;
use Modules\AiAssistant\Database\Seeders\AiTutorSettingsSeeder;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Partner\Database\Seeders\PartnerSettingsSeeder;
use Modules\QuestionBank\Database\Seeders\MedicalKnowledgeTaxonomySeeder;
use Modules\QuestionBank\Database\Seeders\MedicalLicensingExamBlueprintSeeder;

/**
 * Minimal baseline for production bootstrap.
 *
 * No demo users, FAQ, banners, or sample learning content.
 * Create the first admin account manually after seed.
 * Does not wipe the database — see docs/seeding.md.
 *
 *   php artisan app:seed production
 *   php artisan app:seed production --fresh
 *   php artisan db:seed --class=ProductionSeeder
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,

            MedicalLicensingExamBlueprintSeeder::class,
            MedicalKnowledgeTaxonomySeeder::class,

            BillingDatabaseSeeder::class,

            SettingsSeeder::class,
            PartnerSettingsSeeder::class,
            AiTutorSettingsSeeder::class,
            CmsPageSeeder::class,
            MenuSeeder::class,
        ]);

        $this->command->info('Production seed xong. Tạo tài khoản admin thủ công — không seed user cố định.');
    }
}
