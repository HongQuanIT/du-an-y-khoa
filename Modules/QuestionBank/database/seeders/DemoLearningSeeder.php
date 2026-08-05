<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Models\Topic;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Demo dataset for the learning slice:
 * VM14K questions + a student's sessions/attempts/status.
 *
 * Idempotent: safe to re-run (keys on slug/stem/email; skips progress if it
 * already exists). Scout syncing is disabled during the run.
 */
class DemoLearningSeeder extends Seeder
{
    public function run(): void
    {
        Question::withoutSyncingToSearch(function (): void {
            $this->call(TopicTaxonomySeeder::class);
            $this->seedVm14kQuestions();
            $student = $this->resolveDemoStudent();
            $this->seedProgress($student);
            $this->seedHintUsage($student);
        });
    }

    /**
     * Seed questions from the downloaded VM14K Vietnamese MCQ dataset (JSONL).
     *
     * Files are expected at:
     *   Modules/QuestionBank/database/seeders/data/vm14k/data-processed-shuffled*.jsonl
     *
     * To avoid re-parsing on every run, we drop a flag file in storage after success.
     */
    private function seedVm14kQuestions(): void
    {
        $flag = storage_path('app/questionbank_seed/vm14k.flag');
        if (is_file($flag)) {
            return;
        }

        $dir = base_path('Modules/QuestionBank/database/seeders/data/vm14k');
        $files = glob($dir.'/data-processed-shuffled*.jsonl') ?: [];

        if ($files === []) {
            return;
        }

        @mkdir(dirname($flag), 0775, true);

        $limit = (int) env('QUESTIONBANK_VM14K_LIMIT', 1500);
        $limit = $limit > 0 ? $limit : 1500;

        $processed = 0;
        $idx = 0;
        $topics = Topic::query()->get()->keyBy('slug');

        foreach ($files as $file) {
            $handle = fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $row = json_decode($line, true);
                if (! is_array($row)) {
                    continue;
                }

                $stem = (string) ($row['question'] ?? '');
                if ($stem === '') {
                    continue;
                }

                $difficultyLevel = strtolower((string) ($row['difficulty_level'] ?? 'medium'));
                $difficulty = match ($difficultyLevel) {
                    'easy' => Difficulty::Easy,
                    'hard' => Difficulty::Hard,
                    default => Difficulty::Medium,
                };

                $topicId = null;
                $medicalTopic = $row['medical_topic'] ?? [];
                if (is_array($medicalTopic) && ($medicalTopic[0] ?? null) !== null) {
                    $systemSlug = $this->topicSlug((string) ($medicalTopic[1] ?? ''));
                    $specialtySlug = $this->topicSlug((string) $medicalTopic[0]);
                    $topic = $topics->get($systemSlug) ?? $topics->get($specialtySlug);
                    $topicId = $topic?->id;
                }

                $correctIndex = (int) ($row['answer_index'] ?? 0);
                $optionsRaw = $row['options'] ?? [];
                $options = [];

                if (is_array($optionsRaw)) {
                    foreach (array_values($optionsRaw) as $optIdx => $content) {
                        $options[] = [
                            'content' => (string) $content,
                            'is_correct' => (int) $optIdx === $correctIndex,
                            'explanation' => null,
                        ];
                    }
                }

                if (count($options) !== 4) {
                    continue; // keep demo clean: VM14K should always be 4 options
                }

                $question = Question::firstOrCreate(
                    ['stem' => $stem],
                    [
                        'explanation' => null,
                        'difficulty' => $difficulty,
                        'status' => QuestionStatus::Published,
                        'topic_id' => $topicId,
                        'is_free' => $idx % 3 === 0,
                    ],
                );

                if ($question->wasRecentlyCreated) {
                    $this->seedOptions($question, $options);
                }

                $processed++;
                $idx++;

                if ($processed >= $limit) {
                    fclose($handle);
                    file_put_contents($flag, 'ok');

                    return;
                }
            }

            fclose($handle);
        }

        file_put_contents($flag, 'ok');
    }

    /**
     * @param  list<array{content: string, is_correct: bool, explanation?: string}>  $options
     */
    private function seedOptions(Question $question, array $options): void
    {
        $labels = ['A', 'B', 'C', 'D', 'E'];

        foreach (array_values($options) as $index => $option) {
            QuestionOption::create([
                'question_id' => $question->getKey(),
                'label' => $labels[$index] ?? (string) ($index + 1),
                'content' => $option['content'],
                'is_correct' => $option['is_correct'],
                'explanation' => $option['explanation'] ?? null,
                'order' => $index,
            ]);
        }
    }

    /**
     * Resolve (or create) the primary demo student to attach progress to.
     */
    private function resolveDemoStudent(): User
    {
        $student = User::firstOrCreate(
            ['email' => 'student@medlearn.local'],
            ['name' => 'Student', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );

        if (! $student->hasRole(Role::Student->value)) {
            RoleModel::findOrCreate(Role::Student->value, 'web');
            $student->assignRole(Role::Student->value);
        }

        return $student;
    }

    /**
     * Build two completed sessions and one paused session with attempts +
     * per-question status. Skips entirely if the student already has sessions.
     */
    private function seedProgress(User $student): void
    {
        if (QuestionSession::where('user_id', $student->id)->exists()) {
            return;
        }

        /** @var Collection<int, Question> $questions */
        $questions = Question::with('options')
            ->where('status', QuestionStatus::Published)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($questions->isEmpty()) {
            return;
        }

        // Session 1: 10 questions, 7 correct.
        $this->buildSession(
            $student,
            $questions->slice(0, 10)->values(),
            correctTarget: 7,
            status: SessionStatus::Completed,
            daysAgo: 5,
        );

        // Session 2: 10 questions, 5 correct.
        $this->buildSession(
            $student,
            $questions->slice(10, 10)->values(),
            correctTarget: 5,
            status: SessionStatus::Completed,
            daysAgo: 2,
        );

        // Session 3: paused after answering 3 of 8 (Continue Learning).
        $this->buildSession(
            $student,
            $questions->slice(20, 8)->values(),
            correctTarget: 2,
            status: SessionStatus::Paused,
            daysAgo: 0,
            answeredLimit: 3,
        );
    }

    /**
     * @param  Collection<int, Question>  $questions
     */
    private function buildSession(
        User $student,
        Collection $questions,
        int $correctTarget,
        SessionStatus $status,
        int $daysAgo,
        ?int $answeredLimit = null,
    ): void {
        $total = $questions->count();
        $answered = $answeredLimit ?? $total;
        $when = Carbon::now()->subDays($daysAgo);

        $session = QuestionSession::create([
            'user_id' => $student->id,
            'mode' => SessionMode::Study,
            'status' => $status,
            'filters' => ['status' => 'unseen'],
            'question_ids' => $questions->map(fn (Question $q) => $q->getKey())->all(),
            'total' => $total,
            'answered_count' => $answered,
            'correct_count' => 0,
            'time_limit_seconds' => null,
            'paused_state' => $status === SessionStatus::Paused ? ['current_index' => $answered] : null,
            'created_at' => $when,
            'updated_at' => $when,
        ]);

        $correctCount = 0;

        foreach ($questions->values() as $index => $question) {
            if ($index >= $answered) {
                break; // paused: leave remaining questions unanswered
            }

            $shouldBeCorrect = $index < $correctTarget;
            $option = $this->pickOption($question, $shouldBeCorrect);
            $isCorrect = $option?->is_correct ?? false;
            $correctCount += $isCorrect ? 1 : 0;

            QuestionAttempt::create([
                'session_id' => $session->getKey(),
                'user_id' => $student->id,
                'question_id' => $question->getKey(),
                'selected_option_ids' => $option ? [$option->id] : [],
                'is_correct' => $isCorrect,
                'used_hint' => false,
                'time_spent_seconds' => 45 + $index * 3,
                'confidence' => $isCorrect ? 'high' : 'low',
                'flagged' => false,
                'answered_at' => $when,
            ]);

            $this->upsertStatus($student, $question, $isCorrect, $when);
        }

        $session->forceFill(['correct_count' => $correctCount])->save();
    }

    private function pickOption(Question $question, bool $wantCorrect): ?QuestionOption
    {
        $options = $question->options;

        if ($options->isEmpty()) {
            return null;
        }

        $match = $options->first(fn (QuestionOption $o) => $o->is_correct === $wantCorrect);

        return $match ?? $options->first();
    }

    private function upsertStatus(User $student, Question $question, bool $isCorrect, Carbon $when): void
    {
        $status = $isCorrect ? UserQuestionStatus::Correct : UserQuestionStatus::Incorrect;

        UserQuestionStatusModel::updateOrCreate(
            ['user_id' => $student->id, 'question_id' => $question->getKey()],
            [
                'status' => $status,
                'attempts_count' => 1,
                'last_attempt_at' => $when,
                'last_correct_at' => $isCorrect ? $when : null,
            ],
        );
    }

    /**
     * Keep the "answered correctly using hints" filter usable in demo data.
     *
     * This also upgrades older seeded databases where all attempts originally
     * had used_hint=false.
     */
    private function seedHintUsage(User $student): void
    {
        $attemptIds = QuestionAttempt::query()
            ->where('user_id', $student->id)
            ->where('is_correct', true)
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->limit(8)
            ->pluck('id');

        QuestionAttempt::query()
            ->whereIn('id', $attemptIds)
            ->update(['used_hint' => true]);
    }

    private function topicSlug(string $name): string
    {
        return \Illuminate\Support\Str::limit((string) \Illuminate\Support\Str::slug($name), 191, '');
    }
}
