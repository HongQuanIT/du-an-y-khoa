<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standardized 3-level content taxonomy (DAG):
 *   Bài học (lessons) → Môn học (subjects) → Hệ cơ quan (organ_systems)
 *
 * - lesson is the standard knowledge unit; questions attach to lessons.
 * - lesson ↔ subject and subject ↔ organ_system are many-to-many, so a
 *   lesson can belong to multiple subjects and (transitively) multiple systems.
 * - Symptom/concept tagging keeps living on the orthogonal `tags` axis.
 *
 * Replaces the freeform `medical_taxonomy_nodes` tree (dropped in the sibling
 * migration) while keeping the blueprint / core_clinical_topics exam matrix,
 * which now maps to lessons via `core_topic_lessons`.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['organ_systems', 'subjects', 'lessons'] as $table) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->string('slug')->unique();
                    $table->string('code')->nullable();
                    $table->text('description')->nullable();
                    $table->string('status', 20)->default('active');
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->index(['status', 'sort_order']);
                });
            }
        }

        if (! Schema::hasTable('subject_organ_system')) {
            Schema::create('subject_organ_system', function (Blueprint $table): void {
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('organ_system_id')->constrained('organ_systems')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->primary(['subject_id', 'organ_system_id'], 'subject_organ_system_primary');
                $table->index('organ_system_id', 'subject_organ_system_os_idx');
            });
        }

        if (! Schema::hasTable('lesson_subject')) {
            Schema::create('lesson_subject', function (Blueprint $table): void {
                $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->primary(['lesson_id', 'subject_id'], 'lesson_subject_primary');
                $table->index('subject_id', 'lesson_subject_subject_idx');
            });
        }

        if (! Schema::hasTable('question_lesson')) {
            Schema::create('question_lesson', function (Blueprint $table): void {
                $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
                $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['question_id', 'lesson_id'], 'question_lesson_primary');
                $table->index('lesson_id', 'question_lesson_lesson_idx');
            });
        }

        if (! Schema::hasTable('core_topic_lessons')) {
            Schema::create('core_topic_lessons', function (Blueprint $table): void {
                $table->unsignedBigInteger('core_clinical_topic_id');
                $table->unsignedBigInteger('lesson_id');
                $table->timestamps();

                $table->primary(['core_clinical_topic_id', 'lesson_id'], 'core_topic_lessons_primary');
                $table->foreign('core_clinical_topic_id', 'ctl_core_fk')
                    ->references('id')->on('core_clinical_topics')->cascadeOnDelete();
                $table->foreign('lesson_id', 'ctl_lesson_fk')
                    ->references('id')->on('lessons')->cascadeOnDelete();
                $table->index('lesson_id', 'ctl_lesson_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_topic_lessons');
        Schema::dropIfExists('question_lesson');
        Schema::dropIfExists('lesson_subject');
        Schema::dropIfExists('subject_organ_system');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('organ_systems');
    }
};
