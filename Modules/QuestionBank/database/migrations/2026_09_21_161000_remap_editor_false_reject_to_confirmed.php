<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Editor QA chỉ còn Soạn đạt / Soạn lỗi — remap legacy false_reject → confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('question_workflow_events', 'outcome')) {
            return;
        }

        DB::table('question_workflow_events')
            ->where('outcome', 'false_reject')
            ->update(['outcome' => 'confirmed']);
    }

    public function down(): void
    {
        // Irreversible remap.
    }
};
