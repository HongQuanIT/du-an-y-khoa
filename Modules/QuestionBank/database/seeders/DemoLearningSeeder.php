<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Demo progress for the learning slice: one student's sessions, attempts, and status.
 *
 * Idempotent: skips progress when that student already has sessions.
 * Scout syncing is disabled during the run. Questions come from the other QBank seeders.
 */
class DemoLearningSeeder extends Seeder
{
    public function run(): void
    {
        Question::withoutSyncingToSearch(function (): void {
            $this->call(MedicalKnowledgeTaxonomySeeder::class);
            $student = $this->resolveDemoStudent();
            $this->seedProgress($student);
            $this->seedHintUsage($student);
        });
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

}
