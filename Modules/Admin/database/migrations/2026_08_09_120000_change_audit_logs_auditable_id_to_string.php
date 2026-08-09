<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Questions use UUID PKs; nullableMorphs() created BIGINT auditable_id and
 * truncated UUIDs on MySQL. Store morph keys as strings for both int and UUID.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            // SQLite tests: create migration already uses nullableUuidMorphs.
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['auditable_type', 'auditable_id']);
        });

        DB::statement('ALTER TABLE audit_logs MODIFY auditable_id VARCHAR(36) NULL');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['auditable_type', 'auditable_id']);
        });

        DB::statement('ALTER TABLE audit_logs MODIFY auditable_id BIGINT UNSIGNED NULL');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['auditable_type', 'auditable_id']);
        });
    }
};
