<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use App\Models\User;
use App\Support\Enums\Role;
use App\Support\ScopeFilters;
use App\Support\TargetExams;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionScopeType;
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
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
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
    private const QUESTION_TARGET = 200;

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

    /** Seed at most 200 questions from the downloaded VM14K JSONL dataset. */
    private function seedVm14kQuestions(): void
    {
        $flag = storage_path('app/questionbank_seed/vm14k.flag');
        $topics = Topic::query()->get()->keyBy('slug');

        if (app()->environment('testing') || ! is_file($flag)) {
            $dir = base_path('Modules/QuestionBank/database/seeders/data/vm14k');
            $files = glob($dir.'/data-processed-shuffled*.jsonl') ?: [];
            $configuredLimit = (int) env('QUESTIONBANK_VM14K_LIMIT', self::QUESTION_TARGET);
            $initialCount = Question::count();
            $targetCount = min(
                self::QUESTION_TARGET,
                $initialCount + ($configuredLimit > 0 ? $configuredLimit : self::QUESTION_TARGET),
            );
            $idx = $initialCount;

            foreach ($files as $file) {
                $handle = fopen($file, 'rb');
                if ($handle === false) {
                    continue;
                }

                while (($line = fgets($handle)) !== false && $idx < $targetCount) {
                    $row = json_decode(trim($line), true);
                    if (! is_array($row) || ! is_string($row['question'] ?? null) || $row['question'] === '') {
                        continue;
                    }

                    $difficulty = match (strtolower((string) ($row['difficulty_level'] ?? 'medium'))) {
                        'easy' => Difficulty::Easy,
                        'hard' => Difficulty::Hard,
                        default => Difficulty::Medium,
                    };
                    $medicalTopic = $row['medical_topic'] ?? [];
                    $topic = is_array($medicalTopic)
                        ? $topics->get($this->topicSlug((string) ($medicalTopic[1] ?? '')))
                            ?? $topics->get($this->topicSlug((string) ($medicalTopic[0] ?? '')))
                        : null;
                    $correctIndex = (int) ($row['answer_index'] ?? 0);
                    $rawOptions = is_array($row['options'] ?? null) ? array_values($row['options']) : [];

                    if (count($rawOptions) !== 4) {
                        continue;
                    }

                    $question = Question::firstOrCreate(
                        ['stem' => $row['question']],
                        [
                            'explanation' => null,
                            'difficulty' => $difficulty,
                            'status' => QuestionStatus::Published,
                            'topic_id' => $topic?->id,
                            'is_free' => $idx % 3 === 0,
                        ],
                    );

                    if ($question->wasRecentlyCreated) {
                        $this->seedOptions($question, array_map(
                            fn (mixed $content, int $optionIndex): array => [
                                'content' => (string) $content,
                                'is_correct' => $optionIndex === $correctIndex,
                                'explanation' => null,
                            ],
                            $rawOptions,
                            array_keys($rawOptions),
                        ));
                        $idx++;
                    }
                }

                fclose($handle);
                if ($idx >= $targetCount) {
                    break;
                }
            }

            if ($files !== [] && ! app()->environment('testing')) {
                @mkdir(dirname($flag), 0775, true);
                file_put_contents($flag, 'ok');
            }
        }

        $this->rebalanceGeneratedDifficulties();
        $this->seedLongFormLayoutQuestion($topics);
        $this->seedQuestionScopes();
    }

    /**
     * Assign indexed, deterministic facets to every demo question.
     *
     * Two values per catalog keep combined demo filters useful while still
     * proving that an unassigned value excludes a question. Production content
     * can manage the same rows explicitly through its authoring workflow.
     */
    private function seedQuestionScopes(): void
    {
        $examKeys = array_keys(TargetExams::selectable());
        $articleKeys = array_column(ScopeFilters::articles(), 'id');
        $symptomKeys = array_column(ScopeFilters::symptoms(), 'id');
        $now = now()->toDateTimeString();
        $rows = [];

        $questions = Question::query()
            ->where('status', QuestionStatus::Published)
            ->orderBy('id')
            ->get(['id', 'stem'])
            ->values();

        DB::table('question_scopes')
            ->whereIn('question_id', $questions->pluck('id'))
            ->delete();

        $questions->each(function (Question $question, int $index) use (
            &$rows,
            $examKeys,
            $articleKeys,
            $symptomKeys,
            $now,
        ): void {
            $assignments = [
                QuestionScopeType::Exam->value => $this->rotatingKeys($examKeys, intdiv($index, 36), 1),
                QuestionScopeType::Article->value => $this->rotatingKeys($articleKeys, intdiv($index, 6), 2),
                QuestionScopeType::Symptom->value => $this->rotatingKeys($symptomKeys, $index, 3),
            ];

            if (str_starts_with((string) $question->stem, 'A 24-year-old man comes to the emergency department')) {
                $assignments[QuestionScopeType::Exam->value][] = 'usmle-step-2-ck';
                $assignments[QuestionScopeType::Exam->value][] = 'nbme';
                $assignments[QuestionScopeType::Article->value][] = 'pneumonia';
                $assignments[QuestionScopeType::Article->value][] = 'sepsis';
                $assignments[QuestionScopeType::Symptom->value][] = 'dyspnea';
            }

            foreach ($assignments as $type => $keys) {
                foreach (array_values(array_unique($keys)) as $key) {
                    $rows[] = [
                        'question_id' => $question->getKey(),
                        'scope_type' => $type,
                        'scope_key' => $key,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        });

        if ($rows !== []) {
            DB::table('question_scopes')->upsert(
                $rows,
                ['question_id', 'scope_type', 'scope_key'],
                ['updated_at'],
            );
        }
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function rotatingKeys(array $keys, int $index, int $offset): array
    {
        if ($keys === []) {
            return [];
        }

        return array_values(array_unique([
            $keys[$index % count($keys)],
            $keys[($index + $offset) % count($keys)],
        ]));
    }

    /** Keep the VM14K demo bank represented across all five difficulty levels. */
    private function rebalanceGeneratedDifficulties(): void
    {
        $difficulties = Difficulty::cases();

        Question::query()
            ->where('status', QuestionStatus::Published)
            ->orderBy('id')
            ->get()
            ->values()
            ->each(function (Question $question, int $index) use ($difficulties): void {
                $difficulty = $difficulties[$index % count($difficulties)];

                if ($question->difficulty !== $difficulty) {
                    $question->forceFill(['difficulty' => $difficulty])->saveQuietly();
                }
            });
    }

    /**
     * @param  list<array{content: string, is_correct: bool, explanation?: string}>  $options
     */
    private function seedOptions(Question $question, array $options): void
    {
        $labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

        foreach ($options as $index => $option) {
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
     * Seed a deliberately long English vignette to exercise Study/Exam layouts.
     *
     * An unused generated placeholder is repurposed so re-running the demo seed
     * against an existing 200-question database does not increase the total.
     *
     * @param  Collection<string, Topic>  $topics
     */
    private function seedLongFormLayoutQuestion(Collection $topics): void
    {
        $stem = <<<'TEXT'
A 24-year-old man comes to the emergency department because of progressive shortness of breath and intermittent cough with blood-tinged sputum for the past 10 days. During this time, he had three episodes of blood in his urine. Six years ago, he was diagnosed with latent tuberculosis after a positive routine tuberculin skin test, and he was treated accordingly. His maternal aunt has systemic lupus erythematosus. The patient does not take any medications. His temperature is 37.0°C (98.6°F), pulse is 92/min, respirations are 28/min, and blood pressure is 152/90 mm Hg. Diffuse crackles are heard at both lung bases. Laboratory studies show:

Serum
Urea nitrogen    32 mg/dL
Creatinine       3.5 mg/dL

Urine
Protein          2+
Blood            3+
RBC casts        numerous
WBC casts        negative

A chest x-ray shows patchy pulmonary infiltrates bilaterally. A renal biopsy shows linear deposits of IgG along the glomerular basement membrane. Which of the following is the most likely diagnosis?
TEXT;
        $options = [
            [
                'content' => 'Goodpasture syndrome',
                'is_correct' => true,
                'explanation' => 'Anti-GBM disease causes pulmonary hemorrhage and rapidly progressive glomerulonephritis with linear IgG deposition.',
            ],
            ['content' => 'Eosinophilic granulomatosis with polyangiitis', 'is_correct' => false],
            ['content' => 'IgA nephropathy', 'is_correct' => false],
            ['content' => 'Granulomatosis with polyangiitis', 'is_correct' => false],
            ['content' => 'Reactivated tuberculosis', 'is_correct' => false],
            ['content' => 'Microscopic polyangiitis', 'is_correct' => false],
            ['content' => 'Lupus nephritis', 'is_correct' => false],
        ];
        $question = Question::query()->where('stem', $stem)->first();

        if (! $question instanceof Question) {
            $question = Question::query()
                ->where('status', QuestionStatus::Published)
                ->whereNotIn('id', QuestionAttempt::query()->select('question_id'))
                ->orderByDesc('id')
                ->first() ?? new Question;
        }

        $question->forceFill([
            'stem' => $stem,
            'explanation' => 'Goodpasture syndrome (anti-GBM disease) presents with pulmonary hemorrhage and rapidly progressive glomerulonephritis. Linear IgG along the glomerular basement membrane is the classic biopsy finding.',
            'key_info' => [
                'blood-tinged sputum',
                'three episodes of blood in his urine',
                'linear deposits of IgG along the glomerular basement membrane',
            ],
            'attending_tip' => 'Ho ra máu kết hợp tiểu máu, suy thận và IgG lắng đọng dạng đường thẳng dọc màng đáy cầu thận là bộ dấu hiệu điển hình của bệnh kháng màng đáy cầu thận (hội chứng Goodpasture).',
            'difficulty' => Difficulty::VeryHard,
            'status' => QuestionStatus::Published,
            'topic_id' => ($topics['urology'] ?? $topics['nephrology'] ?? null)?->id,
            'is_free' => true,
        ])->save();

        $hasExpectedOptions = $question->options()->count() === count($options)
            && $question->options()
                ->where('content', 'Goodpasture syndrome')
                ->where('is_correct', true)
                ->exists();

        if (! $hasExpectedOptions) {
            $question->options()->delete();
            $this->seedOptions($question, $options);
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
        app(QuestionSessionSnapshots::class)->capture($session);

        $correctCount = 0;

        foreach ($questions->values() as $index => $question) {
            if ($index >= $answered) {
                break; // paused: leave remaining questions unanswered
            }

            $shouldBeCorrect = $index < $correctTarget;
            $option = $this->pickOption($question, $shouldBeCorrect);
            $isCorrect = $option instanceof QuestionOption && $option->is_correct;
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
        return Str::limit((string) Str::slug($name), 191, '');
    }
}
