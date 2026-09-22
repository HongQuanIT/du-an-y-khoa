<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seed ~300 published questions from `data/qbank-demo.xlsx` for learner/QBank QA.
 *
 *   php artisan db:seed --class='Modules\QuestionBank\Database\Seeders\QbankDemoExcelSeeder'
 *
 * Or directly:
 *   php artisan question-bank:seed-from-excel
 */
final class QbankDemoExcelSeeder extends Seeder
{
    public function run(): void
    {
        $exit = Artisan::call('question-bank:seed-from-excel', [
            '--editor' => 'editor@medlearn.local',
            '--publisher' => 'admin@medlearn.local',
        ]);

        if ($this->command !== null) {
            $this->command->getOutput()->write(Artisan::output());
        }

        if ($exit !== 0) {
            $this->command?->error('QbankDemoExcelSeeder failed (exit '.$exit.').');
        }
    }
}
