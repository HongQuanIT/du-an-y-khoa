<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Topic;

/**
 * Large-volume generator for performance / search / pagination testing.
 *
 * Disabled by default; enable with `SEED_VOLUME=true`. Tunables:
 *   SEED_QUESTIONS, SEED_STUDENTS, SEED_ATTEMPTS_PER_STUDENT.
 *
 * Uses chunked raw inserts (no model events) and disables Scout syncing so it
 * stays fast and does not flood Meilisearch during seeding.
 */
class VolumeLearningSeeder extends Seeder
{
    private const CHUNK = 500;

    public function run(): void
    {
        if (! $this->flag('SEED_VOLUME', false)) {
            $this->command?->info('SEED_VOLUME=false → bỏ qua volume seeding.');

            return;
        }

        $questionCount = $this->int('SEED_QUESTIONS', 2000);
        $studentCount = $this->int('SEED_STUDENTS', 50);
        $attemptsPerStudent = $this->int('SEED_ATTEMPTS_PER_STUDENT', 300);

        Question::withoutSyncingToSearch(function () use ($questionCount, $studentCount, $attemptsPerStudent): void {
            $this->command?->info("Volume seeding: {$questionCount} câu hỏi, {$studentCount} học viên...");

            $topicIds = $this->ensureTopics(30);
            $questionIds = $this->generateQuestions($questionCount, $topicIds);
            $studentIds = $this->generateStudents($studentCount);
            $this->generateActivity($studentIds, $questionIds, $attemptsPerStudent);

            $this->command?->info('Volume seeding hoàn tất.');
        });
    }

    /**
     * Ensure at least $min topics exist; return all topic ids.
     *
     * @return list<int>
     */
    private function ensureTopics(int $min): array
    {
        $missing = $min - Topic::count();

        if ($missing > 0) {
            Topic::factory()->count($missing)->create();
        }

        return Topic::query()->pluck('id')->all();
    }

    /**
     * Batch-generate questions + 4 options each via chunked raw inserts.
     *
     * @param  list<int>  $topicIds
     * @return list<string> generated question uuids
     */
    private function generateQuestions(int $count, array $topicIds): array
    {
        $now = now()->toDateTimeString();
        $difficulties = Difficulty::values();
        $ids = [];
        $questionRows = [];
        $optionRows = [];

        for ($i = 0; $i < $count; $i++) {
            $id = (string) Str::uuid();
            $ids[] = $id;

            $questionRows[] = [
                'id' => $id,
                'stem' => "[vol] Câu hỏi hiệu năng #{$i} — ".Str::random(24).'?',
                'explanation' => 'Giải thích tự sinh cho câu hỏi hiệu năng.',
                'difficulty' => $difficulties[$i % count($difficulties)],
                'status' => QuestionStatus::Published->value,
                'topic_id' => $topicIds[$i % count($topicIds)],
                'is_free' => $i % 4 === 0,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $correct = $i % 4;
            foreach (['A', 'B', 'C', 'D'] as $j => $label) {
                $optionRows[] = [
                    'question_id' => $id,
                    'label' => $label,
                    'content' => "Phương án {$label}",
                    'is_correct' => $j === $correct,
                    'explanation' => null,
                    'order' => $j,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($questionRows) >= self::CHUNK) {
                DB::table('questions')->insert($questionRows);
                DB::table('question_options')->insert($optionRows);
                $questionRows = [];
                $optionRows = [];
            }
        }

        if ($questionRows !== []) {
            DB::table('questions')->insert($questionRows);
            DB::table('question_options')->insert($optionRows);
        }

        return $ids;
    }

    /**
     * @return list<int> generated student user ids
     */
    private function generateStudents(int $count): array
    {
        $ids = [];

        User::factory()->count($count)->create()->each(function (User $user) use (&$ids): void {
            $user->assignRole(Role::Student->value);
            $ids[] = $user->id;
        });

        return $ids;
    }

    /**
     * For each student, sample distinct questions and split into sessions of 50,
     * generating attempts + per-question status via chunked raw inserts.
     *
     * @param  list<int>  $studentIds
     * @param  list<string>  $questionIds
     */
    private function generateActivity(array $studentIds, array $questionIds, int $attemptsPerStudent): void
    {
        if ($questionIds === []) {
            return;
        }

        $now = now()->toDateTimeString();
        $perStudent = min($attemptsPerStudent, count($questionIds));
        $sessionSize = 50;

        $sessionRows = [];
        $attemptRows = [];
        $statusRows = [];

        $flush = function () use (&$sessionRows, &$attemptRows, &$statusRows): void {
            if ($sessionRows !== []) {
                DB::table('question_sessions')->insert($sessionRows);
                $sessionRows = [];
            }
            if ($attemptRows !== []) {
                DB::table('question_attempts')->insert($attemptRows);
                $attemptRows = [];
            }
            if ($statusRows !== []) {
                DB::table('question_status')->insert($statusRows);
                $statusRows = [];
            }
        };

        foreach ($studentIds as $studentId) {
            $sample = collect($questionIds)->shuffle()->take($perStudent)->all();

            foreach (array_chunk($sample, $sessionSize) as $chunk) {
                $sessionId = (string) Str::uuid();
                $correctCount = 0;

                foreach ($chunk as $questionId) {
                    $isCorrect = random_int(1, 100) <= 60; // ~60% correct
                    $correctCount += $isCorrect ? 1 : 0;

                    $attemptRows[] = [
                        'session_id' => $sessionId,
                        'user_id' => $studentId,
                        'question_id' => $questionId,
                        'selected_option_ids' => json_encode([random_int(1, 4)]),
                        'is_correct' => $isCorrect,
                        'used_hint' => false,
                        'time_spent_seconds' => random_int(15, 150),
                        'confidence' => null,
                        'flagged' => false,
                        'answered_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $statusRows[] = [
                        'user_id' => $studentId,
                        'question_id' => $questionId,
                        'status' => $isCorrect ? 'correct' : 'incorrect',
                        'attempts_count' => 1,
                        'last_attempt_at' => $now,
                        'last_correct_at' => $isCorrect ? $now : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $sessionRows[] = [
                    'id' => $sessionId,
                    'user_id' => $studentId,
                    'mode' => 'study',
                    'status' => 'completed',
                    'filters' => json_encode(['status' => 'unseen']),
                    'question_ids' => json_encode($chunk),
                    'total' => count($chunk),
                    'answered_count' => count($chunk),
                    'correct_count' => $correctCount,
                    'time_limit_seconds' => null,
                    'paused_state' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($attemptRows) >= self::CHUNK) {
                    $flush();
                }
            }
        }

        $flush();
    }

    private function flag(string $key, bool $default): bool
    {
        return filter_var(env($key, $default), FILTER_VALIDATE_BOOL);
    }

    private function int(string $key, int $default): int
    {
        $value = env($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
