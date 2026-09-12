<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive learning rollup on question_status:
 * - last_seen_at: last meaningful exposure (answered or omitted)
 * - correct/wrong/omitted_count: fast Weakness inputs
 * - last_served_*: cooldown when a question enters a user session
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            if (! Schema::hasColumn('question_status', 'correct_count')) {
                $table->unsignedInteger('correct_count')->default(0)->after('attempts_count');
            }
            if (! Schema::hasColumn('question_status', 'wrong_count')) {
                $table->unsignedInteger('wrong_count')->default(0)->after('correct_count');
            }
            if (! Schema::hasColumn('question_status', 'omitted_count')) {
                $table->unsignedInteger('omitted_count')->default(0)->after('wrong_count');
            }
            if (! Schema::hasColumn('question_status', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('last_attempt_at');
            }
            if (! Schema::hasColumn('question_status', 'last_served_at')) {
                $table->timestamp('last_served_at')->nullable()->after('last_seen_at');
            }
            if (! Schema::hasColumn('question_status', 'last_served_session_id')) {
                $table->foreignUuid('last_served_session_id')
                    ->nullable()
                    ->after('last_served_at')
                    ->constrained('question_sessions')
                    ->nullOnDelete();
            }
        });

        Schema::table('question_status', function (Blueprint $table): void {
            $table->index(['user_id', 'last_seen_at'], 'question_status_user_last_seen_idx');
            $table->index(['user_id', 'last_served_at'], 'question_status_user_last_served_idx');
        });

        $this->backfillFromAttempts();
        $this->backfillLastSeenFallback();
        $this->backfillLastServedFromSessions();
    }

    public function down(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            $table->dropIndex('question_status_user_last_seen_idx');
            $table->dropIndex('question_status_user_last_served_idx');

            if (Schema::hasColumn('question_status', 'last_served_session_id')) {
                $table->dropConstrainedForeignId('last_served_session_id');
            }
        });

        Schema::table('question_status', function (Blueprint $table): void {
            $drop = [];
            foreach (['last_served_at', 'last_seen_at', 'omitted_count', 'wrong_count', 'correct_count'] as $column) {
                if (Schema::hasColumn('question_status', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    private function backfillFromAttempts(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::table('question_attempts')
                ->select('user_id', 'question_id')
                ->selectRaw('SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_count')
                ->selectRaw('SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) as wrong_count')
                ->selectRaw('SUM(CASE WHEN is_correct IS NULL THEN 1 ELSE 0 END) as omitted_count')
                ->selectRaw('MAX(COALESCE(answered_at, updated_at, created_at)) as last_seen_at')
                ->groupBy('user_id', 'question_id')
                ->get();

            foreach ($rows as $row) {
                DB::table('question_status')
                    ->where('user_id', $row->user_id)
                    ->where('question_id', $row->question_id)
                    ->update([
                        'correct_count' => (int) $row->correct_count,
                        'wrong_count' => (int) $row->wrong_count,
                        'omitted_count' => (int) $row->omitted_count,
                        'last_seen_at' => $row->last_seen_at,
                    ]);
            }

            return;
        }

        DB::statement('
            UPDATE question_status AS qs
            INNER JOIN (
                SELECT
                    user_id,
                    question_id,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS correct_count,
                    SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) AS wrong_count,
                    SUM(CASE WHEN is_correct IS NULL THEN 1 ELSE 0 END) AS omitted_count,
                    MAX(COALESCE(answered_at, updated_at, created_at)) AS last_seen_at
                FROM question_attempts
                GROUP BY user_id, question_id
            ) AS agg
                ON agg.user_id = qs.user_id
                AND agg.question_id = qs.question_id
            SET
                qs.correct_count = agg.correct_count,
                qs.wrong_count = agg.wrong_count,
                qs.omitted_count = agg.omitted_count,
                qs.last_seen_at = agg.last_seen_at
        ');
    }

    private function backfillLastSeenFallback(): void
    {
        DB::table('question_status')
            ->whereNull('last_seen_at')
            ->whereNotNull('last_attempt_at')
            ->update([
                'last_seen_at' => DB::raw('last_attempt_at'),
            ]);
    }

    private function backfillLastServedFromSessions(): void
    {
        $latest = [];

        DB::table('question_sessions')
            ->orderBy('id')
            ->select(['id', 'user_id', 'question_ids', 'created_at'])
            ->chunkById(200, function ($sessions) use (&$latest): void {
                foreach ($sessions as $session) {
                    $questionIds = json_decode((string) $session->question_ids, true);
                    if (! is_array($questionIds)) {
                        continue;
                    }

                    $createdAt = (string) $session->created_at;
                    $userId = (int) $session->user_id;
                    $sessionId = (string) $session->id;

                    foreach ($questionIds as $questionId) {
                        $questionId = (string) $questionId;
                        $key = $userId.'|'.$questionId;
                        $prev = $latest[$key]['at'] ?? null;
                        if ($prev === null || $createdAt >= $prev) {
                            $latest[$key] = [
                                'user_id' => $userId,
                                'question_id' => $questionId,
                                'at' => $createdAt,
                                'session_id' => $sessionId,
                            ];
                        }
                    }
                }
            });

        foreach (array_chunk(array_values($latest), 200) as $chunk) {
            foreach ($chunk as $row) {
                DB::table('question_status')
                    ->where('user_id', $row['user_id'])
                    ->where('question_id', $row['question_id'])
                    ->update([
                        'last_served_at' => $row['at'],
                        'last_served_session_id' => $row['session_id'],
                    ]);
            }
        }
    }
};
