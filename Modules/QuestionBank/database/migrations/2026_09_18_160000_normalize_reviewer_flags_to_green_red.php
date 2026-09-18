<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reviewer flags are binary green/red. Map legacy yellow → green (kept note).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('questions', 'reviewer_1_flag')) {
            DB::table('questions')->where('reviewer_1_flag', 'yellow')->update(['reviewer_1_flag' => 'green']);
            DB::table('questions')->where('reviewer_2_flag', 'yellow')->update(['reviewer_2_flag' => 'green']);
        }

        if (Schema::hasTable('question_reviewer_flags')) {
            DB::table('question_reviewer_flags')->where('flag', 'yellow')->update(['flag' => 'green']);
        }
    }

    public function down(): void
    {
        // Irreversible: yellow was intentionally removed; no restore.
    }
};
