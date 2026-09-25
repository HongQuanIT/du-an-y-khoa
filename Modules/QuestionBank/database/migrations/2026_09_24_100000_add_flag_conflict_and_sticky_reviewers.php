<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag conflict workflow: sticky reviewers after dual-red, reject reason codes,
 * audit trail for flag changes / reaffirmations in conflict.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('questions', 'sticky_reviewer_1_id')) {
                $table->unsignedBigInteger('sticky_reviewer_1_id')->nullable()->after('reviewer_2_note');
            }
            if (! Schema::hasColumn('questions', 'sticky_reviewer_2_id')) {
                $table->unsignedBigInteger('sticky_reviewer_2_id')->nullable()->after('sticky_reviewer_1_id');
            }
            if (! Schema::hasColumn('questions', 'reject_reason_code')) {
                $table->string('reject_reason_code', 32)->nullable()->after('rejected_by_role');
            }
        });

        Schema::table('question_reviewer_flags', function (Blueprint $table): void {
            if (! Schema::hasColumn('question_reviewer_flags', 'reaffirmed_at')) {
                $table->timestamp('reaffirmed_at')->nullable()->after('reviewed_at');
            }
        });

        if (! Schema::hasTable('question_flag_change_events')) {
            Schema::create('question_flag_change_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('question_id');
                $table->unsignedInteger('review_cycle');
                $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
                $table->string('from_flag', 16)->nullable();
                $table->string('to_flag', 16);
                $table->text('note')->nullable();
                $table->boolean('reaffirmed')->default(false);
                $table->boolean('responsibility_acked')->default(false);
                $table->string('ack_text_version', 32)->nullable();
                $table->timestamps();

                $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
                $table->index(['question_id', 'review_cycle'], 'q_flag_change_q_cycle_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('question_flag_change_events');

        Schema::table('question_reviewer_flags', function (Blueprint $table): void {
            if (Schema::hasColumn('question_reviewer_flags', 'reaffirmed_at')) {
                $table->dropColumn('reaffirmed_at');
            }
        });

        Schema::table('questions', function (Blueprint $table): void {
            foreach (['sticky_reviewer_1_id', 'sticky_reviewer_2_id', 'reject_reason_code'] as $column) {
                if (Schema::hasColumn('questions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
