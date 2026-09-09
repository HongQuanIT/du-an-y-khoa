<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions attach to lessons equally — no primary lesson distinction.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('question_lesson')) {
            return;
        }

        if (Schema::hasColumn('question_lesson', 'is_primary')) {
            Schema::table('question_lesson', function (Blueprint $table): void {
                $table->dropColumn('is_primary');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('question_lesson')) {
            return;
        }

        if (! Schema::hasColumn('question_lesson', 'is_primary')) {
            Schema::table('question_lesson', function (Blueprint $table): void {
                $table->boolean('is_primary')->default(false);
            });
        }
    }
};
