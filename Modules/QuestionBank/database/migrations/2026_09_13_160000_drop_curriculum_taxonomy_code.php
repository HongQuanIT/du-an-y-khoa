<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slug is the only stable catalog key (import, seed, snapshots).
 * code duplicated slug in practice and is removed from the three curriculum tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['organ_systems', 'subjects', 'lessons'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'code')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('code');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['organ_systems', 'subjects', 'lessons'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'code')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->string('code')->nullable()->after('slug');
                });
            }
        }
    }
};
