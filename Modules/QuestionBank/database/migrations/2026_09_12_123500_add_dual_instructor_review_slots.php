<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('questions', 'instructor_review_cycle')) {
                $table->unsignedInteger('instructor_review_cycle')
                    ->default(0)
                    ->after('instructor_id');
            }
            if (! Schema::hasColumn('questions', 'instructor_1_id')) {
                $table->foreignId('instructor_1_id')
                    ->nullable()
                    ->after('instructor_review_cycle')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('questions', 'instructor_1_decision')) {
                $table->string('instructor_1_decision', 16)
                    ->nullable()
                    ->after('instructor_1_id');
            }
            if (! Schema::hasColumn('questions', 'instructor_2_id')) {
                $table->foreignId('instructor_2_id')
                    ->nullable()
                    ->after('instructor_1_decision')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('questions', 'instructor_2_decision')) {
                $table->string('instructor_2_decision', 16)
                    ->nullable()
                    ->after('instructor_2_id');
            }
        });

        Schema::create('question_instructor_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedInteger('review_cycle');
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 16);
            $table->text('note')->nullable();
            $table->char('content_fingerprint', 64)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'review_cycle', 'instructor_id'], 'qir_question_cycle_instructor_uq');
            $table->index(['question_id', 'review_cycle'], 'qir_question_cycle_idx');
            $table->index(['instructor_id', 'decision'], 'qir_instructor_decision_idx');
        });

        $this->backfillLegacySlots();
    }

    public function down(): void
    {
        Schema::dropIfExists('question_instructor_reviews');

        Schema::table('questions', function (Blueprint $table): void {
            if (Schema::hasColumn('questions', 'instructor_2_id')) {
                $table->dropConstrainedForeignId('instructor_2_id');
            }
            if (Schema::hasColumn('questions', 'instructor_1_id')) {
                $table->dropConstrainedForeignId('instructor_1_id');
            }
            foreach (['instructor_2_decision', 'instructor_1_decision', 'instructor_review_cycle'] as $column) {
                if (Schema::hasColumn('questions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillLegacySlots(): void
    {
        $rows = DB::table('questions')
            ->whereNotNull('instructor_id')
            ->whereNull('instructor_1_id')
            ->get(['id', 'instructor_id', 'status', 'rejected_by_role', 'content_fingerprint']);

        foreach ($rows as $row) {
            $decision = $row->status === 'rejected' && $row->rejected_by_role === 'instructor'
                ? 'rejected'
                : 'approved';

            DB::table('questions')
                ->where('id', $row->id)
                ->update([
                    'instructor_1_id' => $row->instructor_id,
                    'instructor_1_decision' => $decision,
                ]);

            DB::table('question_instructor_reviews')->insert([
                'question_id' => $row->id,
                'review_cycle' => 0,
                'instructor_id' => $row->instructor_id,
                'decision' => $decision,
                'content_fingerprint' => $row->content_fingerprint,
                'reviewed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
