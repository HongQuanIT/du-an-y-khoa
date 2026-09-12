<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Support\QuestionCodeAllocator;

/**
 * Assign sequential Q##### codes to questions that still lack a stable code.
 */
return new class extends Migration
{
    public function up(): void
    {
        $allocator = app(QuestionCodeAllocator::class);

        $ids = DB::table('questions')
            ->whereNull('code')
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            DB::table('questions')
                ->where('id', $id)
                ->whereNull('code')
                ->update(['code' => $allocator->allocate()]);
        }
    }

    public function down(): void
    {
        // Codes are immutable identifiers — do not strip on rollback.
    }
};
