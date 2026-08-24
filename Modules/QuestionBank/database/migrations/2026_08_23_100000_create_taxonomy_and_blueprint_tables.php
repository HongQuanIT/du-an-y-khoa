<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blueprints')) {
            Schema::create('blueprints', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['status', 'sort_order']);
            });
        }

        if (! Schema::hasTable('blueprint_sections')) {
            Schema::create('blueprint_sections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('blueprint_id')->constrained('blueprints')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['blueprint_id', 'slug']);
                $table->index(['blueprint_id', 'status', 'sort_order'], 'bp_sections_status_sort_idx');
            });
        }

        if (! Schema::hasTable('core_clinical_topics')) {
            Schema::create('core_clinical_topics', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('blueprint_section_id')->constrained('blueprint_sections')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['blueprint_section_id', 'slug']);
                $table->index(['blueprint_section_id', 'status', 'sort_order'], 'cct_section_status_sort_idx');
            });
        }

        if (! Schema::hasTable('medical_taxonomies')) {
            Schema::create('medical_taxonomies', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('medical_taxonomy_nodes')) {
            Schema::create('medical_taxonomy_nodes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('medical_taxonomy_id')->constrained('medical_taxonomies')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('medical_taxonomy_nodes')->nullOnDelete();
                $table->unsignedBigInteger('legacy_topic_id')->nullable()->unique();
                $table->string('name');
                $table->string('slug');
                $table->string('code')->nullable();
                $table->string('node_type')->nullable();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['medical_taxonomy_id', 'slug']);
                $table->index(['medical_taxonomy_id', 'parent_id', 'sort_order'], 'mtn_taxonomy_parent_sort_idx');
                $table->index(['medical_taxonomy_id', 'status'], 'mtn_taxonomy_status_idx');
                $table->index('parent_id');
            });
        }

        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('type')->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['status', 'type']);
            });
        }

        if (! Schema::hasTable('question_blueprint_topics')) {
            Schema::create('question_blueprint_topics', function (Blueprint $table): void {
                $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
                $table->foreignId('core_clinical_topic_id')->constrained('core_clinical_topics')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['question_id', 'core_clinical_topic_id']);
                $table->index('core_clinical_topic_id');
            });
        }

        if (! Schema::hasTable('question_medical_topics')) {
            Schema::create('question_medical_topics', function (Blueprint $table): void {
                $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
                $table->foreignId('medical_taxonomy_node_id')->constrained('medical_taxonomy_nodes')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['question_id', 'medical_taxonomy_node_id']);
                $table->index('medical_taxonomy_node_id');
            });
        }

        if (! Schema::hasTable('question_tags')) {
            Schema::create('question_tags', function (Blueprint $table): void {
                $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['question_id', 'tag_id']);
                $table->index('tag_id');
            });
        }

        if (! Schema::hasTable('core_topic_medical_taxonomy_nodes')) {
            Schema::create('core_topic_medical_taxonomy_nodes', function (Blueprint $table): void {
                $table->unsignedBigInteger('core_clinical_topic_id');
                $table->unsignedBigInteger('medical_taxonomy_node_id');
                $table->timestamps();

                $table->primary(['core_clinical_topic_id', 'medical_taxonomy_node_id'], 'ct_mtn_primary');
                $table->foreign('core_clinical_topic_id', 'ct_mtn_core_fk')
                    ->references('id')->on('core_clinical_topics')->cascadeOnDelete();
                $table->foreign('medical_taxonomy_node_id', 'ct_mtn_node_fk')
                    ->references('id')->on('medical_taxonomy_nodes')->cascadeOnDelete();
                $table->index('medical_taxonomy_node_id', 'ct_mtn_node_idx');
            });
        }

        $this->migrateLegacyTopics();
    }

    public function down(): void
    {
        Schema::dropIfExists('core_topic_medical_taxonomy_nodes');
        Schema::dropIfExists('question_tags');
        Schema::dropIfExists('question_medical_topics');
        Schema::dropIfExists('question_blueprint_topics');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('medical_taxonomy_nodes');
        Schema::dropIfExists('medical_taxonomies');
        Schema::dropIfExists('core_clinical_topics');
        Schema::dropIfExists('blueprint_sections');
        Schema::dropIfExists('blueprints');
    }

    private function migrateLegacyTopics(): void
    {
        if (! Schema::hasTable('topics')) {
            return;
        }

        if (DB::table('medical_taxonomies')->where('code', 'medlearn')->exists()) {
            return;
        }

        $taxonomyId = DB::table('medical_taxonomies')->insertGetId([
            'name' => 'MedLearn Medical Taxonomy',
            'code' => 'medlearn',
            'description' => 'Migrated from legacy topics table.',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $topics = DB::table('topics')->orderBy('id')->get(['id', 'parent_id', 'name', 'slug', 'type', 'order']);
        if ($topics->isEmpty()) {
            return;
        }

        $nodeIdByTopicId = [];

        foreach ($topics->whereNull('parent_id') as $topic) {
            $nodeIdByTopicId[(int) $topic->id] = $this->insertLegacyNode($taxonomyId, $topic, null);
        }

        $remaining = $topics->whereNotNull('parent_id')->values();
        $guard = 0;

        while ($remaining->isNotEmpty() && $guard < 20) {
            $guard++;
            $next = collect();

            foreach ($remaining as $topic) {
                $parentTopicId = (int) $topic->parent_id;
                if (! isset($nodeIdByTopicId[$parentTopicId])) {
                    $next->push($topic);

                    continue;
                }

                $nodeIdByTopicId[(int) $topic->id] = $this->insertLegacyNode(
                    $taxonomyId,
                    $topic,
                    $nodeIdByTopicId[$parentTopicId],
                );
            }

            $remaining = $next;
        }

        foreach ($remaining as $topic) {
            $nodeIdByTopicId[(int) $topic->id] = $this->insertLegacyNode($taxonomyId, $topic, null);
        }

        if (! Schema::hasTable('question_topic')) {
            return;
        }

        $existingPairs = DB::table('question_medical_topics')
            ->select('question_id', 'medical_taxonomy_node_id')
            ->get()
            ->map(fn ($row): string => $row->question_id.'|'.$row->medical_taxonomy_node_id)
            ->flip();

        $pivotRows = DB::table('question_topic')->get(['question_id', 'topic_id']);
        $now = now();

        foreach ($pivotRows as $row) {
            $nodeId = $nodeIdByTopicId[(int) $row->topic_id] ?? null;
            if ($nodeId === null) {
                continue;
            }

            $key = $row->question_id.'|'.$nodeId;
            if ($existingPairs->has($key)) {
                continue;
            }

            DB::table('question_medical_topics')->insert([
                'question_id' => $row->question_id,
                'medical_taxonomy_node_id' => $nodeId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $existingPairs->put($key, true);
        }
    }

    private function insertLegacyNode(int $taxonomyId, object $topic, ?int $parentNodeId): int
    {
        $slug = $this->uniqueNodeSlug($taxonomyId, (string) $topic->slug, (int) $topic->id);

        return (int) DB::table('medical_taxonomy_nodes')->insertGetId([
            'medical_taxonomy_id' => $taxonomyId,
            'parent_id' => $parentNodeId,
            'legacy_topic_id' => (int) $topic->id,
            'name' => $topic->name,
            'slug' => $slug,
            'code' => null,
            'node_type' => $topic->type ?? null,
            'description' => null,
            'sort_order' => (int) ($topic->order ?? 0),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function uniqueNodeSlug(int $taxonomyId, string $slug, int $topicId): string
    {
        $base = Str::slug($slug !== '' ? $slug : 'topic-'.$topicId);
        if ($base === '') {
            $base = 'topic-'.$topicId;
        }

        $candidate = $base;
        $suffix = 1;

        while (
            DB::table('medical_taxonomy_nodes')
                ->where('medical_taxonomy_id', $taxonomyId)
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
};
