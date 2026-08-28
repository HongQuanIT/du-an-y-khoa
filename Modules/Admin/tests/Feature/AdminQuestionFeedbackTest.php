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
            'message' => 'Đáp án này cần kiểm tra lại.',
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.question-feedback.index'))
            ->assertOk()
            ->assertSee('Đáp án này cần kiểm tra lại.')
            ->assertSee($option->content);

        $this->actingAsStaff($admin)
            ->patch(route('admin.question-feedback.update-status', $feedback), [
                'status' => QuestionFeedback::STATUS_RESOLVED,
            ])
            ->assertRedirect();

        $this->assertSame(QuestionFeedback::STATUS_RESOLVED, $feedback->fresh()->status);
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
