<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive V2 (filter → group → quota):
 * - recent_results: cửa sổ đúng/sai gần nhất (weakness)
 * - wrong_streak / thrash_blocked_until: tạm tránh khi sai liên tiếp
 * - content_version: phiên bản nội dung lúc học (reset khi publish mới)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            if (! Schema::hasColumn('question_status', 'recent_results')) {
                $table->json('recent_results')->nullable()->after('wrong_count');
            }
            if (! Schema::hasColumn('question_status', 'wrong_streak')) {
                $table->unsignedSmallInteger('wrong_streak')->default(0)->after('recent_results');
            }
            if (! Schema::hasColumn('question_status', 'thrash_blocked_until')) {
                $table->timestamp('thrash_blocked_until')->nullable()->after('wrong_streak');
            }
            if (! Schema::hasColumn('question_status', 'content_version')) {
                $table->unsignedInteger('content_version')->nullable()->after('thrash_blocked_until');
            }
        });

        Schema::table('question_status', function (Blueprint $table): void {
            $table->index(['user_id', 'thrash_blocked_until'], 'question_status_user_thrash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            $table->dropIndex('question_status_user_thrash_idx');
            foreach (['content_version', 'thrash_blocked_until', 'wrong_streak', 'recent_results'] as $column) {
                if (Schema::hasColumn('question_status', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
