<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * exam_flag (exam pool) → is_priority ("Câu ưu tiên" cho chữa đề livestream).
 * Bài thi lấy từ ngân hàng đã xuất bản, không còn pool private+exam_flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('questions')) {
            return;
        }

        if (! Schema::hasColumn('questions', 'exam_flag') || Schema::hasColumn('questions', 'is_priority')) {
            return;
        }

        Schema::table('questions', function (Blueprint $table): void {
            if (Schema::hasIndex('questions', ['status', 'exam_flag', 'created_at'])) {
                $table->dropIndex(['status', 'exam_flag', 'created_at']);
            }
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->renameColumn('exam_flag', 'is_priority');
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->index(['status', 'is_priority', 'created_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('questions')) {
            return;
        }

        if (! Schema::hasColumn('questions', 'is_priority') || Schema::hasColumn('questions', 'exam_flag')) {
            return;
        }

        Schema::table('questions', function (Blueprint $table): void {
            if (Schema::hasIndex('questions', ['status', 'is_priority', 'created_at'])) {
                $table->dropIndex(['status', 'is_priority', 'created_at']);
            }
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->renameColumn('is_priority', 'exam_flag');
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->index(['status', 'exam_flag', 'created_at']);
        });
    }
};
