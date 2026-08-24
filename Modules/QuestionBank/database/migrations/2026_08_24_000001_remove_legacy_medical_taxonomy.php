<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Giữ một danh mục y khoa duy nhất: medlearn-medical-taxonomy.
 * Xóa bộ legacy migrate từ bảng topics (code = medlearn).
 */
return new class extends Migration
{
    private const LEGACY_CODE = 'medlearn';

    public function up(): void
    {
        if (! Schema::hasTable('medical_taxonomies')) {
            return;
        }

        $legacyId = DB::table('medical_taxonomies')->where('code', self::LEGACY_CODE)->value('id');
        if ($legacyId === null) {
            return;
        }

        $nodeIds = DB::table('medical_taxonomy_nodes')
            ->where('medical_taxonomy_id', $legacyId)
            ->pluck('id');

        if ($nodeIds->isNotEmpty()) {
            DB::table('question_medical_topics')
                ->whereIn('medical_taxonomy_node_id', $nodeIds)
                ->delete();

            if (Schema::hasTable('core_topic_medical_taxonomy_nodes')) {
                DB::table('core_topic_medical_taxonomy_nodes')
                    ->whereIn('medical_taxonomy_node_id', $nodeIds)
                    ->delete();
            }

            DB::table('medical_taxonomy_nodes')
                ->where('medical_taxonomy_id', $legacyId)
                ->delete();
        }

        DB::table('medical_taxonomies')->where('id', $legacyId)->delete();
    }

    public function down(): void
    {
        // Không khôi phục dữ liệu legacy đã xóa.
    }
};
