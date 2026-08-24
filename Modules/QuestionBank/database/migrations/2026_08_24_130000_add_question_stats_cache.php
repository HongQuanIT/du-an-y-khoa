<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('questions', 'stats_cache')) {
                $table->json('stats_cache')->nullable()->after('cloned_from_version');
            }
            if (! Schema::hasColumn('questions', 'stats_updated_at')) {
                $table->timestamp('stats_updated_at')->nullable()->after('stats_cache');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            if (Schema::hasColumn('questions', 'stats_updated_at')) {
                $table->dropColumn('stats_updated_at');
            }
            if (Schema::hasColumn('questions', 'stats_cache')) {
                $table->dropColumn('stats_cache');
            }
        });
    }
};
