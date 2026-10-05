<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\QuestionBank\Support\MemoryStability;

/**
 * Forgetting-curve state on question_status.
 * memory_stability_days: per learner×question stability S.
 * last_graded_at: clock for R(t); omit must not move it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            if (! Schema::hasColumn('question_status', 'memory_stability_days')) {
                $table->decimal('memory_stability_days', 8, 2)->nullable()->after('last_seen_at');
            }
            if (! Schema::hasColumn('question_status', 'last_graded_at')) {
                $table->timestamp('last_graded_at')->nullable()->after('memory_stability_days');
            }
        });

        Schema::table('question_status', function (Blueprint $table): void {
            $table->index(['user_id', 'last_graded_at'], 'question_status_user_last_graded_idx');
        });

        $this->backfillFromGradedAttempts();
    }

    public function down(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            $table->dropIndex('question_status_user_last_graded_idx');
        });

        Schema::table('question_status', function (Blueprint $table): void {
            $drop = [];
            foreach (['last_graded_at', 'memory_stability_days'] as $column) {
                if (Schema::hasColumn('question_status', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    private function backfillFromGradedAttempts(): void
    {
        $userId = null;
        $questionId = null;
        $stability = null;
        $lastGraded = null;

        $flush = function () use (&$userId, &$questionId, &$stability, &$lastGraded): void {
            if ($userId === null || $questionId === null || $stability === null) {
                return;
            }

            DB::table('question_status')
                ->where('user_id', $userId)
                ->where('question_id', $questionId)
                ->update([
                    'memory_stability_days' => round($stability, 2),
                    'last_graded_at' => $lastGraded,
                ]);
        };

        $attempts = DB::table('question_attempts')
            ->whereNotNull('is_correct')
            ->orderBy('user_id')
            ->orderBy('question_id')
            ->orderBy('answered_at')
            ->orderBy('id')
            ->select(['user_id', 'question_id', 'is_correct', 'answered_at'])
            ->cursor();

        foreach ($attempts as $attempt) {
            $samePair = $userId === (int) $attempt->user_id
                && (string) $questionId === (string) $attempt->question_id;

            if (! $samePair) {
                $flush();
                $userId = (int) $attempt->user_id;
                $questionId = (string) $attempt->question_id;
                $stability = null;
                $lastGraded = null;
            }

            $answeredAt = $attempt->answered_at;
            $stability = MemoryStability::afterGrade(
                $stability,
                (int) $attempt->is_correct === 1,
                $lastGraded,
                $answeredAt,
            );
            $lastGraded = $answeredAt ?? $lastGraded;
        }

        $flush();
    }
};
