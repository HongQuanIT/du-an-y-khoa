<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionStatus as UserQuestionStatusModel;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class WeakTopicSessionTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    public function test_student_can_start_a_topic_session_prioritized_by_incorrect_frequency(): void
    {
        $student = User::factory()->create();
        $topic = $this->makeMedicalNode(['name' => 'Tim mạch']);
        $otherTopic = $this->makeMedicalNode(['name' => 'Hô hấp']);
        $mostIncorrect = $this->questionFor($topic);
        $secondIncorrect = $this->questionFor($topic);
        $this->questionFor($topic);
        $otherTopicQuestion = $this->questionFor($otherTopic);

        $this->recordIncorrectAttempts($student, $mostIncorrect, 3);
        $this->recordIncorrectAttempts($student, $secondIncorrect, 2);
        $this->recordIncorrectAttempts($student, $otherTopicQuestion, 5);

        $response = $this->actingAs($student)->post(route('qbank.weak-topics.session', $topic), [
            'count' => 3,
        ]);

        $session = QuestionSession::query()
            ->where('user_id', $student->getKey())
            ->where('status', SessionStatus::Active)
            ->latest('created_at')
            ->firstOrFail();

        $response->assertRedirect(route('qbank.session', $session));
        $this->assertSame(SessionSource::WeakTopics, $session->source);
        $this->assertSame(
            [$mostIncorrect->getKey(), $secondIncorrect->getKey()],
            $session->question_ids,
        );
        $this->assertSame([$topic->getKey()], $session->filters['medical_taxonomy_node_ids']);
        $this->assertCount(2, $session->snapshots);

        UserQuestionStatusModel::query()
            ->where('user_id', $student->getKey())
            ->where('question_id', $mostIncorrect->getKey())
            ->update(['status' => UserQuestionStatus::Correct->value]);

        $this->actingAs($student)->post(route('qbank.weak-topics.session', $topic), ['count' => 3]);

        $nextSession = QuestionSession::query()
            ->where('user_id', $student->getKey())
            ->where('status', SessionStatus::Active)
            ->latest('created_at')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame([$secondIncorrect->getKey()], $nextSession->question_ids);
    }

    public function test_exam_mistakes_use_the_existing_exam_repeat_flow(): void
    {
        $student = User::factory()->create();
        $topic = $this->makeMedicalNode(['name' => 'Tim mạch']);
        $question = $this->questionFor($topic);
        $answeredAt = now()->subMinute();
        $original = QuestionSession::factory()->for($student)->exam()->create([
            'status' => SessionStatus::Completed,
            'question_ids' => [$question->getKey()],
            'total' => 1,
            'answered_count' => 1,
            'correct_count' => 0,
        ]);
        QuestionAttempt::factory()->incorrect()->create([
            'session_id' => $original->getKey(),
            'user_id' => $student->getKey(),
            'question_id' => $question->getKey(),
            'answered_at' => $answeredAt,
        ]);
        UserQuestionStatusModel::query()->create([
            'user_id' => $student->getKey(),
            'question_id' => $question->getKey(),
            'status' => UserQuestionStatus::Incorrect,
            'attempts_count' => 1,
            'last_attempt_at' => $answeredAt,
        ]);

        $response = $this->actingAs($student)->post(route('qbank.weak-topics.session', $topic), [
            'count' => 10,
        ]);

        $repeated = QuestionSession::query()
            ->where('user_id', $student->getKey())
            ->where('status', SessionStatus::Active)
            ->firstOrFail();

        $response->assertRedirect(route('exam.session', $repeated));
        $this->assertSame(SessionMode::Exam, $repeated->mode);
        $this->assertSame([$question->getKey()], $repeated->question_ids);
        $this->assertSame((string) $original->getKey(), $repeated->filters['repeated_from_session_id']);
        $this->assertSame(['incorrect'], $repeated->filters['repeat_statuses']);
        $this->assertCount(1, $repeated->snapshots);
    }

    private function questionFor(MedicalTaxonomyNode $topic): Question
    {
        $question = Question::factory()
            ->withOptions()
            ->create([
                'status' => QuestionStatus::Published,
                'is_free' => true,
            ]);
        $question->medicalTaxonomyNodes()->attach($topic->getKey());

        return $question;
    }

    private function recordIncorrectAttempts(User $student, Question $question, int $count): void
    {
        for ($attempt = 0; $attempt < $count; $attempt++) {
            $session = QuestionSession::factory()->for($student)->create([
                'status' => SessionStatus::Completed,
                'question_ids' => [$question->getKey()],
                'total' => 1,
                'answered_count' => 1,
                'correct_count' => 0,
            ]);

            QuestionAttempt::factory()->incorrect()->create([
                'session_id' => $session->getKey(),
                'user_id' => $student->getKey(),
                'question_id' => $question->getKey(),
                'answered_at' => now()->subMinutes($count - $attempt),
            ]);

            UserQuestionStatusModel::query()->updateOrCreate(
                [
                    'user_id' => $student->getKey(),
                    'question_id' => $question->getKey(),
                ],
                [
                    'status' => UserQuestionStatus::Incorrect,
                    'attempts_count' => $attempt + 1,
                    'last_attempt_at' => now()->subMinutes($count - $attempt),
                ],
            );
        }
    }
}
