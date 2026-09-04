<?php

namespace Modules\AiAssistant\Database\Seeders;

use Illuminate\Database\Seeder;

class AiAssistantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AiTutorSettingsSeeder::class,
        ]);
    }
}
