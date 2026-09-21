<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionSession;
use Tests\TestCase;

final class AdminQuestionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_answer_feedback_and_update_status(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create(['name' => 'Học viên phản hồi']);
        $question = Question::factory()->free()->withOptions()->create([
            'stem' => 'Câu hỏi có feedback đáp án',
        ]);
        $option = $question->options()->firstOrFail();
        $session = QuestionSession::factory()->for($student)->create([
            'question_ids' => [$question->getKey()],
        ]);
        $feedback = QuestionFeedback::query()->create([
            'user_id' => $student->getKey(),
            'question_id' => $question->getKey(),
            'question_session_id' => $session->getKey(),
            'question_option_id' => $option->getKey(),
            'target' => 'answer',
            'category' => 'incorrect',
            'message' => str_repeat('Đáp án này cần kiểm tra lại. ', 12),
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.question-feedback.index'))
            ->assertOk()
            ->assertSee('Đáp án này cần kiểm tra lại.')
            ->assertSee($option->content)
            ->assertSee('line-clamp-2', false)
            ->assertSee("x-text=\"expanded ? 'Thu gọn' : 'Chi tiết'\"", false);

        $this->actingAsStaff($admin)
            ->patch(route('admin.question-feedback.update-status', $feedback), [
                'status' => QuestionFeedback::STATUS_RESOLVED,
            ])
            ->assertRedirect();

        $this->assertSame(QuestionFeedback::STATUS_RESOLVED, $feedback->fresh()->status);
    }

    public function test_admin_can_filter_question_feedback_with_multiple_values(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = User::factory()->create();
        $question = Question::factory()->free()->withOptions()->create();
        $option = $question->options()->firstOrFail();
        $session = QuestionSession::factory()->for($student)->create([
            'question_ids' => [$question->getKey()],
        ]);

        $first = QuestionFeedback::query()->create([
            'user_id' => $student->getKey(),
            'question_id' => $question->getKey(),
            'question_session_id' => $session->getKey(),
            'question_option_id' => $option->getKey(),
            'target' => 'answer',
            'category' => 'incorrect',
            'message' => 'Phản hồi đáp án cần xem xét',
            'status' => QuestionFeedback::STATUS_PENDING,
        ]);
        $second = QuestionFeedback::query()->create([
            'user_id' => $student->getKey(),
            'question_id' => $question->getKey(),
            'question_session_id' => $session->getKey(),
            'target' => 'question',
            'category' => 'missing',
            'message' => 'Phản hồi câu hỏi cần bổ sung',
            'status' => QuestionFeedback::STATUS_REVIEWING,
        ]);
        QuestionFeedback::query()->create([
            'user_id' => $student->getKey(),
            'question_id' => $question->getKey(),
            'question_session_id' => $session->getKey(),
            'target' => 'knowledge',
            'category' => 'technical',
            'message' => 'Phản hồi không phù hợp',
            'status' => QuestionFeedback::STATUS_RESOLVED,
        ]);

        $this->actingAsStaff($admin)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.question-feedback.index', [
                'status' => [QuestionFeedback::STATUS_PENDING, QuestionFeedback::STATUS_REVIEWING],
                'target' => ['answer', 'question'],
                'category' => ['incorrect', 'missing'],
            ]))
            ->assertOk()
            ->assertSee('id="question-feedback-results-region"', false)
            ->assertViewHas('feedbackItems', fn ($items): bool => $items->pluck('id')->sort()->values()->all() === collect([
                $first->id,
                $second->id,
            ])->sort()->values()->all());
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->enrollTwoFactor($user);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }

    private function enrollTwoFactor(User $user): void
    {
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);
    }
}
