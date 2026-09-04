<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Database\Seeders\BannerSeeder;
use Modules\Admin\Database\Seeders\CmsPageSeeder;
use Modules\Admin\Database\Seeders\FaqSeeder;
use Modules\Admin\Database\Seeders\MenuSeeder;
use Modules\Admin\Database\Seeders\SettingsSeeder;
use Modules\AiAssistant\Database\Seeders\AiTutorSettingsSeeder;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Library\Database\Seeders\LibraryDatabaseSeeder;
use Modules\Partner\Database\Seeders\PartnerDatabaseSeeder;
use Modules\Partner\Database\Seeders\PartnerSettingsSeeder;
use Modules\QuestionBank\Database\Seeders\QuestionBankDatabaseSeeder;
use Modules\Search\Database\Seeders\SearchDatabaseSeeder;
use Modules\StudyPlan\Database\Seeders\StudyPlanDatabaseSeeder;

/**
 * Full local/dev dataset (demo questions, library, study plan, banners, …).
 * Does not wipe the database — see docs/seeding.md.
 *
 *   php artisan app:seed local
 *   php artisan app:seed local --fresh
 *   php artisan app:seed staging|production
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Accounts you log in with during development.
        $this->call(UserSeeder::class);

        // Learning slice: topics, questions/options, sessions/attempts/status.
        $this->call(QuestionBankDatabaseSeeder::class);

        // Library articles and the unified global search projection.
        $this->call(LibraryDatabaseSeeder::class);
        $this->call(SearchDatabaseSeeder::class);

        // Study plan on top of that slice: an active plan with history + schedule.
        $this->call(StudyPlanDatabaseSeeder::class);

        $this->call(BillingDatabaseSeeder::class);
        $this->call(PartnerDatabaseSeeder::class);

        $this->call(SettingsSeeder::class);
        $this->call(PartnerSettingsSeeder::class);
        $this->call(AiTutorSettingsSeeder::class);
        $this->call(FaqSeeder::class);
        $this->call(CmsPageSeeder::class);
        $this->call(BannerSeeder::class);
        $this->call(MenuSeeder::class);

        $this->command->info('Đăng nhập dev: student@medlearn.local / password (admin@medlearn.local cho khu vực admin).');
    }
}
