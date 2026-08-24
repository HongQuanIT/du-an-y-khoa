<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exam_topics')) {
            return;
        }

        Schema::create('exam_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('core_clinical_topic_id')->constrained('core_clinical_topics')->cascadeOnDelete();
            $table->unsignedInteger('question_count');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'core_clinical_topic_id']);
            $table->index(['exam_id', 'sort_order']);
        });

        if (! Schema::hasColumn('exam_question', 'core_clinical_topic_id')) {
            Schema::table('exam_question', function (Blueprint $table): void {
                $table->foreignId('core_clinical_topic_id')
                    ->nullable()
                    ->after('question_id')
                    ->constrained('core_clinical_topics')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_question', 'core_clinical_topic_id')) {
            Schema::table('exam_question', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('core_clinical_topic_id');
            });
        }

        Schema::dropIfExists('exam_topics');
    }
};
