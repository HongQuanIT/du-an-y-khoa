<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-affirm `in_flag_review` after the RBAC commit dropped the enum case.
 * Existing rows keep that status; no remap to pending_publish.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Keep rows that already sit in the reviewer flag queue.
        // (No-op write: documents intent and refreshes updated_at for ops visibility.)
        DB::table('questions')
            ->where('status', 'in_flag_review')
            ->update(['updated_at' => now()]);
    }

    public function down(): void
    {
        // Do not strip status values on rollback — enum removal would break casting again.
    }
};
