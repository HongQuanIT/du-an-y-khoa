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
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Topic;
use Tests\TestCase;

final class AdminQuestionManagementTest extends TestCase
{
    use RefreshDatabase;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->topic = Topic::query()->create([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-admin-test',
            'type' => 'specialty',
            'order' => 1,
        ]);
    }

    public function test_editor_can_create_draft_question(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $response = $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), $this->payload());

        $question = Question::query()->first();
        $this->assertNotNull($question);
        $response->assertRedirect(route('admin.questions.edit', $question));

        $this->assertSame(QuestionStatus::Draft, $question->status);
        $this->assertCount(4, $question->options);
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.question.create']);
    }

    public function test_editor_can_submit_for_review_but_cannot_publish(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $question = $this->makeDraftQuestion();

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::InReview->value,
            ])
            ->assertRedirect();

        $this->assertSame(QuestionStatus::InReview, $question->fresh()->status);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_publish_question(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makeDraftQuestion();

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertRedirect();

        $this->assertSame(QuestionStatus::Published, $question->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.question.status_change']);
    }

    public function test_questions_index_filters_by_status(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $this->makeDraftQuestion();

        $this->actingAsStaff($admin)
            ->get(route('admin.questions.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('Nháp');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'stem' => 'Bệnh nhân 55 tuổi đau ngực. Chẩn đoán nào phù hợp nhất?',
            'explanation' => 'Giải thích lâm sàng đầy đủ.',
            'attending_tip' => 'Nhớ ECG sớm.',
            'difficulty' => Difficulty::Medium->value,
            'topic_id' => $this->topic->id,
            'is_free' => '1',
            'options' => [
                ['content' => 'ACS', 'is_correct' => '1', 'explanation' => 'Đúng'],
                ['content' => 'GERD', 'is_correct' => '0', 'explanation' => 'Sai'],
                ['content' => 'Lo lắng', 'is_correct' => '0'],
                ['content' => 'Viêm phổi', 'is_correct' => '0'],
            ],
        ];
    }

    private function makeDraftQuestion(): Question
    {
        $question = Question::factory()->draft()->create([
            'topic_id' => $this->topic->id,
            'stem' => 'Stem draft test',
            'explanation' => 'Explanation draft',
            'difficulty' => Difficulty::Easy,
        ]);

        foreach (['A đúng', 'B', 'C', 'D'] as $i => $content) {
            $question->options()->create([
                'label' => chr(65 + $i),
                'content' => $content,
                'is_correct' => $i === 0,
                'order' => $i + 1,
            ]);
        }

        return $question->fresh(['options']);
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }
}
