<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QA outcomes for editor submit events (mirror instructor/reviewer adjudication).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_workflow_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('question_workflow_events', 'outcome')) {
                $table->string('outcome', 24)->default('pending')->after('note');
                $table->string('outcome_source', 16)->nullable()->after('outcome');
                $table->foreignId('outcome_by')->nullable()->after('outcome_source')->constrained('users')->nullOnDelete();
                $table->timestamp('outcome_at')->nullable()->after('outcome_by');
                $table->text('outcome_note')->nullable()->after('outcome_at');
                $table->char('content_fingerprint', 64)->nullable()->after('outcome_note');
                $table->index(['event_type', 'outcome', 'occurred_at'], 'qwe_type_outcome_occurred_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('question_workflow_events', function (Blueprint $table): void {
            if (Schema::hasColumn('question_workflow_events', 'outcome')) {
                $table->dropConstrainedForeignId('outcome_by');
                $table->dropIndex('qwe_type_outcome_occurred_idx');
                $table->dropColumn([
                    'outcome',
                    'outcome_source',
                    'outcome_at',
                    'outcome_note',
                    'content_fingerprint',
                ]);
            }
        });
    }
};
