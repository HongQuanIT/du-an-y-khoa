<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_reviewer_flags', function (Blueprint $table): void {
            if (! Schema::hasColumn('question_reviewer_flags', 'outcome')) {
                $table->string('outcome', 24)->default('pending')->after('note');
                $table->string('outcome_source', 16)->nullable()->after('outcome');
                $table->foreignId('outcome_by')->nullable()->after('outcome_source')->constrained('users')->nullOnDelete();
                $table->timestamp('outcome_at')->nullable()->after('outcome_by');
                $table->text('outcome_note')->nullable()->after('outcome_at');
                $table->index(['flag', 'outcome', 'reviewed_at'], 'qrf_flag_outcome_reviewed_idx');
            }
        });

        Schema::table('question_instructor_reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('question_instructor_reviews', 'outcome')) {
                $table->string('outcome', 24)->default('pending')->after('note');
                $table->string('outcome_source', 16)->nullable()->after('outcome');
                $table->foreignId('outcome_by')->nullable()->after('outcome_source')->constrained('users')->nullOnDelete();
                $table->timestamp('outcome_at')->nullable()->after('outcome_by');
                $table->text('outcome_note')->nullable()->after('outcome_at');
                $table->index(['decision', 'outcome', 'reviewed_at'], 'qir_decision_outcome_reviewed_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('question_reviewer_flags', function (Blueprint $table): void {
            if (Schema::hasColumn('question_reviewer_flags', 'outcome')) {
                $table->dropConstrainedForeignId('outcome_by');
                $table->dropIndex('qrf_flag_outcome_reviewed_idx');
                $table->dropColumn(['outcome', 'outcome_source', 'outcome_at', 'outcome_note']);
            }
        });

        Schema::table('question_instructor_reviews', function (Blueprint $table): void {
            if (Schema::hasColumn('question_instructor_reviews', 'outcome')) {
                $table->dropConstrainedForeignId('outcome_by');
                $table->dropIndex('qir_decision_outcome_reviewed_idx');
                $table->dropColumn(['outcome', 'outcome_source', 'outcome_at', 'outcome_note']);
            }
        });
    }
};
