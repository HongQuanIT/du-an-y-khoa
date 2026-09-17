<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_topic_lessons')) {
            return;
        }

        if (! Schema::hasColumn('core_topic_lessons', 'is_priority')) {
            Schema::table('core_topic_lessons', function (Blueprint $table): void {
                $table->boolean('is_priority')->default(true)->after('lesson_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('core_topic_lessons') && Schema::hasColumn('core_topic_lessons', 'is_priority')) {
            Schema::table('core_topic_lessons', function (Blueprint $table): void {
                $table->dropColumn('is_priority');
            });
        }
    }
};
