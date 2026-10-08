<?php

declare(strict_types=1);

namespace Modules\Exam\Database\Seeders;

use Illuminate\Database\Seeder;

/** Seed only the local `abcd` exam catalog with 600 matching demo questions. */
final class AbcdExamQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $this->callWith(BlueprintExamQuestionSeeder::class, ['catalogCode' => 'abcd']);
    }
}
