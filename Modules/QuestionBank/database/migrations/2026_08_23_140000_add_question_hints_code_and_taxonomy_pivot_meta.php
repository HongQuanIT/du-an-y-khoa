<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completes question taxonomy architecture without duplicating existing tables:
 * - questions.code for stable question identifiers (e.g. CARDIO-STEMI-001)
 * - question_hints as ordered hint rows (key_info remains for learner BC)
 * - relationship_type / is_primary on question_medical_topics pivot
 * - node_type index for filtered lookups
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('questions') && ! Schema::hasColumn('questions', 'code')) {
            Schema::table('questions', function (Blueprint $table): void {
                $table->string('code')->nullable()->unique()->after('id');
            });
        }

        if (! Schema::hasTable('question_hints')) {
            Schema::create('question_hints', function (Blueprint $table): void {
                $table->id();
                $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
                $table->text('content');
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['question_id', 'sort_order']);
                $table->index(['question_id', 'status']);
            });
        }

        if (Schema::hasTable('question_medical_topics')) {
            Schema::table('question_medical_topics', function (Blueprint $table): void {
                if (! Schema::hasColumn('question_medical_topics', 'relationship_type')) {
                    $table->string('relationship_type', 32)->nullable()->after('medical_taxonomy_node_id');
                }
                if (! Schema::hasColumn('question_medical_topics', 'is_primary')) {
                    $table->boolean('is_primary')->nullable()->after('relationship_type');
                }
            });

            if (! $this->indexExists('question_medical_topics', 'qmt_relationship_type_idx')) {
                Schema::table('question_medical_topics', function (Blueprint $table): void {
                    $table->index('relationship_type', 'qmt_relationship_type_idx');
                });
            }
        }

        if (Schema::hasTable('medical_taxonomy_nodes') && ! $this->indexExists('medical_taxonomy_nodes', 'mtn_node_type_idx')) {
            Schema::table('medical_taxonomy_nodes', function (Blueprint $table): void {
                $table->index('node_type', 'mtn_node_type_idx');
            });
        }

        $this->backfillHintsFromKeyInfo();
    }

    public function down(): void
    {
        Schema::dropIfExists('question_hints');

        if (Schema::hasTable('question_medical_topics')) {
            Schema::table('question_medical_topics', function (Blueprint $table): void {
                if (Schema::hasColumn('question_medical_topics', 'is_primary')) {
                    $table->dropColumn('is_primary');
                }
                if (Schema::hasColumn('question_medical_topics', 'relationship_type')) {
                    $table->dropIndex('qmt_relationship_type_idx');
                    $table->dropColumn('relationship_type');
                }
            });
        }

        if (Schema::hasTable('medical_taxonomy_nodes') && $this->indexExists('medical_taxonomy_nodes', 'mtn_node_type_idx')) {
            Schema::table('medical_taxonomy_nodes', function (Blueprint $table): void {
                $table->dropIndex('mtn_node_type_idx');
            });
        }

        if (Schema::hasTable('questions') && Schema::hasColumn('questions', 'code')) {
            Schema::table('questions', function (Blueprint $table): void {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }

    private function backfillHintsFromKeyInfo(): void
    {
        if (! Schema::hasTable('question_hints') || ! Schema::hasColumn('questions', 'key_info')) {
            return;
        }

        $now = now();

        DB::table('questions')
            ->whereNotNull('key_info')
            ->orderBy('id')
            ->chunkById(100, function ($questions) use ($now): void {
                foreach ($questions as $question) {
                    $existing = DB::table('question_hints')
                        ->where('question_id', $question->id)
                        ->exists();

                    if ($existing) {
                        continue;
                    }

                    $raw = $question->key_info;
                    $items = is_string($raw) ? json_decode($raw, true) : $raw;
                    if (! is_array($items) || $items === []) {
                        continue;
                    }

                    $sort = 1;
                    foreach ($items as $item) {
                        $content = trim(strip_tags((string) $item));
                        if ($content === '') {
                            continue;
                        }

                        DB::table('question_hints')->insert([
                            'question_id' => $question->id,
                            'content' => $content,
                            'sort_order' => $sort,
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $sort++;
                    }
                }
            });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (method_exists(Schema::class, 'hasIndex')) {
            return Schema::hasIndex($table, $indexName);
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("pragma index_list('{$table}')");

            foreach ($rows as $row) {
                $name = is_object($row) ? ($row->name ?? null) : ($row['name'] ?? null);
                if ($name === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();
        $rows = DB::select(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $indexName],
        );

        return $rows !== [];
    }
};
