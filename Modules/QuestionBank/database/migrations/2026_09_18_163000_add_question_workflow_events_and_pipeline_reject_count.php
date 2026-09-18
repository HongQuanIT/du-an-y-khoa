<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow events that are not already stored in instructor_reviews / reviewer_flags:
 * submit, admin_reject, publish.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_workflow_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('question_id');
            $table->unsignedInteger('review_cycle')->default(0);
            $table->unsignedInteger('published_version')->nullable();
            $table->string('event_type', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 32)->nullable();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->index(['question_id', 'review_cycle'], 'qwe_question_cycle_idx');
            $table->index(['question_id', 'occurred_at'], 'qwe_question_occurred_idx');
            $table->index(['event_type', 'occurred_at'], 'qwe_type_occurred_idx');
        });

        Schema::table('questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('questions', 'pipeline_reject_count')) {
                $table->unsignedInteger('pipeline_reject_count')
                    ->default(0)
                    ->after('instructor_review_cycle');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_workflow_events');

        Schema::table('questions', function (Blueprint $table): void {
            if (Schema::hasColumn('questions', 'pipeline_reject_count')) {
                $table->dropColumn('pipeline_reject_count');
            }
        });
    }
};
