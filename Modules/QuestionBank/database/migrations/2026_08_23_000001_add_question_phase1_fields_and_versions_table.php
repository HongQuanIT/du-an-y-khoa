<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('questions', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('questions', 'reviewer_id')) {
                $table->foreignId('reviewer_id')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('questions', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('reviewer_id');
            }
            if (! Schema::hasColumn('questions', 'exam_flag')) {
                $table->boolean('exam_flag')->default(false)->after('is_free');
            }
            if (! Schema::hasColumn('questions', 'cloned_from_id')) {
                $table->foreignUuid('cloned_from_id')->nullable()->after('exam_flag')->constrained('questions')->nullOnDelete();
            }
            if (! Schema::hasColumn('questions', 'cloned_from_version')) {
                $table->unsignedInteger('cloned_from_version')->nullable()->after('cloned_from_id');
            }

            $table->index(['status', 'exam_flag', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            if (Schema::hasColumn('questions', 'cloned_from_id')) {
                $table->dropForeign(['cloned_from_id']);
            }
            if (Schema::hasColumn('questions', 'updated_by')) {
                $table->dropForeign(['updated_by']);
            }
            if (Schema::hasColumn('questions', 'reviewer_id')) {
                $table->dropForeign(['reviewer_id']);
            }
            $table->dropIndex(['status', 'exam_flag', 'created_at']);
            $table->dropColumn(array_filter([
                Schema::hasColumn('questions', 'updated_by') ? 'updated_by' : null,
                Schema::hasColumn('questions', 'reviewer_id') ? 'reviewer_id' : null,
                Schema::hasColumn('questions', 'rejection_reason') ? 'rejection_reason' : null,
                Schema::hasColumn('questions', 'exam_flag') ? 'exam_flag' : null,
                Schema::hasColumn('questions', 'cloned_from_id') ? 'cloned_from_id' : null,
                Schema::hasColumn('questions', 'cloned_from_version') ? 'cloned_from_version' : null,
            ]));
        });
    }
};
