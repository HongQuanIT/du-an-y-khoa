<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->foreignId('blueprint_id')
                ->nullable()
                ->after('id')
                ->constrained('blueprints')
                ->nullOnDelete();
        });

        Schema::table('exam_topics', function (Blueprint $table): void {
            $table->json('difficulty_counts')->nullable()->after('question_count');
        });
    }

    public function down(): void
    {
        Schema::table('exam_topics', function (Blueprint $table): void {
            $table->dropColumn('difficulty_counts');
        });

        Schema::table('exams', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('blueprint_id');
        });
    }
};
