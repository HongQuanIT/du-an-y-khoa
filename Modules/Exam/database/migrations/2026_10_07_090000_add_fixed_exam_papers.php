<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->string('kind', 16)->default('legacy');
            $table->json('paper_snapshot')->nullable();
            $table->json('matrix_snapshot')->nullable();
        });
        Schema::table('exam_catalogs', function (Blueprint $table): void {
            $table->foreignId('sample_exam_id')->nullable()->constrained('exams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_catalogs', fn (Blueprint $table) => $table->dropConstrainedForeignId('sample_exam_id'));
        Schema::table('exams', fn (Blueprint $table) => $table->dropColumn(['kind', 'paper_snapshot', 'matrix_snapshot']));
    }
};
