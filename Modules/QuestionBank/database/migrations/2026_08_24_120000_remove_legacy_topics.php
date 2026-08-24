<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove legacy hệ cơ quan topics; mastery rolls up by medical taxonomy node.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rebuildTopicMastery();
        $this->dropQuestionTopicPivot();
        $this->dropQuestionsTopicId();
        $this->dropLegacyTopicIdOnMedicalNodes();
        $this->dropTopicsTable();
    }

    public function down(): void
    {
        // Irreversible: legacy topics and pivot data are not restored.
    }

    private function rebuildTopicMastery(): void
    {
        if (! Schema::hasTable('topic_mastery')) {
            $this->createTopicMasteryWithMedicalNode();

            return;
        }

        if (Schema::hasColumn('topic_mastery', 'medical_taxonomy_node_id')
            && ! Schema::hasColumn('topic_mastery', 'topic_id')) {
            return;
        }

        Schema::drop('topic_mastery');
        $this->createTopicMasteryWithMedicalNode();
    }

    private function createTopicMasteryWithMedicalNode(): void
    {
        Schema::create('topic_mastery', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('medical_taxonomy_node_id')
                ->constrained('medical_taxonomy_nodes')
                ->cascadeOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->decimal('correct_rate', 5, 2)->default(0);
            $table->unsignedTinyInteger('mastery_level')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->json('trend')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'medical_taxonomy_node_id'], 'topic_mastery_user_node_unique');
            $table->index(['user_id', 'correct_rate']);
        });
    }

    private function dropQuestionTopicPivot(): void
    {
        if (Schema::hasTable('question_topic')) {
            Schema::drop('question_topic');
        }
    }

    private function dropQuestionsTopicId(): void
    {
        if (! Schema::hasTable('questions') || ! Schema::hasColumn('questions', 'topic_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            foreach (Schema::getForeignKeys('questions') as $foreignKey) {
                if (in_array('topic_id', $foreignKey['columns'], true)) {
                    Schema::table('questions', function (Blueprint $table) use ($foreignKey): void {
                        $table->dropForeign($foreignKey['name']);
                    });
                }
            }
        }

        foreach (Schema::getIndexes('questions') as $index) {
            if (in_array('topic_id', $index['columns'], true) && ! ($index['primary'] ?? false)) {
                Schema::table('questions', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index['columns']);
                });
            }
        }

        if (Schema::hasColumn('questions', 'topic_id')) {
            Schema::table('questions', function (Blueprint $table): void {
                $table->dropColumn('topic_id');
            });
        }
    }

    private function dropLegacyTopicIdOnMedicalNodes(): void
    {
        if (! Schema::hasTable('medical_taxonomy_nodes')
            || ! Schema::hasColumn('medical_taxonomy_nodes', 'legacy_topic_id')) {
            return;
        }

        foreach (Schema::getIndexes('medical_taxonomy_nodes') as $index) {
            if (in_array('legacy_topic_id', $index['columns'], true) && ! ($index['primary'] ?? false)) {
                Schema::table('medical_taxonomy_nodes', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index['name']);
                });
            }
        }

        Schema::table('medical_taxonomy_nodes', function (Blueprint $table): void {
            $table->dropColumn('legacy_topic_id');
        });
    }

    private function dropTopicsTable(): void
    {
        if (Schema::hasTable('topics')) {
            Schema::drop('topics');
        }
    }
};
