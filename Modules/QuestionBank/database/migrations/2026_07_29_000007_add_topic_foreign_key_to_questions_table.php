<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('questions') || ! Schema::hasColumn('questions', 'topic_id')) {
            return;
        }

        Schema::table('questions', function (Blueprint $table) {
            // Legacy bridge: primary topic FK (removed by 2026_08_24_120000_remove_legacy_topics).
            $table->foreign('topic_id')->references('id')->on('topics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('questions') || ! Schema::hasColumn('questions', 'topic_id')) {
            return;
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['topic_id']);
        });
    }
};
