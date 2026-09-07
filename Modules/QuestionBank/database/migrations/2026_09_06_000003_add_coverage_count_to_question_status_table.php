<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            // Kept separate from lifetime attempts so existing learning
            // history does not distort a learner's current coverage round.
            $table->unsignedInteger('coverage_count')->default(0)->after('attempts_count');
        });
    }

    public function down(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            $table->dropColumn('coverage_count');
        });
    }
};
