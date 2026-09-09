<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the freeform medical_taxonomy_nodes tree in favour of the
 * standardized organ_systems / subjects / lessons model.
 *
 * topic_mastery now rolls up on lesson_id instead of medical_taxonomy_node_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rebuildTopicMasteryOnLesson();

        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('core_topic_medical_taxonomy_nodes');
        Schema::dropIfExists('question_medical_topics');
        Schema::dropIfExists('medical_taxonomy_nodes');
        Schema::dropIfExists('medical_taxonomies');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Irreversible: the legacy medical taxonomy tables are not restored.
    }

    private function rebuildTopicMasteryOnLesson(): void
    {
        if (Schema::hasTable('topic_mastery')
            && Schema::hasColumn('topic_mastery', 'lesson_id')) {
            return;
        }

        if (Schema::hasTable('topic_mastery')) {
            Schema::drop('topic_mastery');
        }

        Schema::create('topic_mastery', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->decimal('correct_rate', 5, 2)->default(0);
            $table->unsignedTinyInteger('mastery_level')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->json('trend')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id'], 'topic_mastery_user_lesson_unique');
            $table->index(['user_id', 'correct_rate']);
        });
    }
};
