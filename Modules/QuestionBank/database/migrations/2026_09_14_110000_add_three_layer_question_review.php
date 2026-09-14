<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_subject', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'subject_id']);
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->foreignId('assigned_instructor_id')->nullable()->after('instructor_id')->constrained('users')->nullOnDelete();
            $table->string('instructor_decision', 16)->nullable()->after('assigned_instructor_id');
            $table->text('instructor_note')->nullable()->after('instructor_decision');
            $table->timestamp('instructor_reviewed_at')->nullable()->after('instructor_note');

            $table->foreignId('reviewer_1_id')->nullable()->after('instructor_2_decision')->constrained('users')->nullOnDelete();
            $table->string('reviewer_1_flag', 16)->nullable()->after('reviewer_1_id');
            $table->text('reviewer_1_note')->nullable()->after('reviewer_1_flag');
            $table->foreignId('reviewer_2_id')->nullable()->after('reviewer_1_note')->constrained('users')->nullOnDelete();
            $table->string('reviewer_2_flag', 16)->nullable()->after('reviewer_2_id');
            $table->text('reviewer_2_note')->nullable()->after('reviewer_2_flag');
        });

        Schema::create('question_reviewer_flags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('question_id');
            $table->unsignedInteger('review_cycle');
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('flag', 16);
            $table->text('note')->nullable();
            $table->string('content_fingerprint', 64)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->unique(['question_id', 'review_cycle', 'reviewer_id'], 'question_reviewer_flags_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_reviewer_flags');

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_instructor_id');
            $table->dropConstrainedForeignId('reviewer_1_id');
            $table->dropConstrainedForeignId('reviewer_2_id');
            $table->dropColumn([
                'instructor_decision',
                'instructor_note',
                'instructor_reviewed_at',
                'reviewer_1_flag',
                'reviewer_1_note',
                'reviewer_2_flag',
                'reviewer_2_note',
            ]);
        });

        Schema::dropIfExists('instructor_subject');
    }
};
