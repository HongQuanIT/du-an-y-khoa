<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_create_publish_and_student_can_find_question_in_qbank(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $admin = $this->staffUser(Role::Admin);
        $student = $this->studentUser();

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), $this->payload())
            ->assertRedirect();

        $question = Question::query()->latest('id')->firstOrFail();
        $this->assertSame(QuestionStatus::Draft, $question->status);
        $this->assertSame(4, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Published->value,
            ])
            ->assertRedirect();

        $published = $question->fresh(['options', 'topic']);
        $this->assertSame(QuestionStatus::Published, $published->status);
        $this->assertSame('Bệnh nhân 55 tuổi đau ngực. Chẩn đoán nào phù hợp nhất?', strip_tags($published->stem));
        $this->assertSame(['đau ngực', 'Chẩn đoán nào phù hợp nhất?'], array_values(array_map('strip_tags', $published->key_info ?? [])));
        $this->assertSame('Giải thích lâm sàng đầy đủ.', strip_tags($published->explanation));
        $this->assertSame('Nhớ ECG sớm.', strip_tags($published->attending_tip));
        $this->assertSame(Difficulty::Medium, $published->difficulty);
        $this->assertSame($this->topic->id, $published->topic_id);
        $this->assertTrue($published->is_free);

        $this->actingAs($student)
            ->get(route('qbank.index', ['q' => 'đau ngực']))
            ->assertOk()
            ->assertSee('Tìm trong ngân hàng câu hỏi')
            ->assertSee('<mark>đau ngực</mark>', false)
            ->assertSee('Bệnh nhân 55 tuổi', false)
            ->assertSee('Miễn phí', false);

        $this->assertStringNotContainsString('Bản nháp', (string) $published->status->label());
        $this->assertTrue(Str::contains(strip_tags($published->stem), 'đau ngực'));
    }

    public function test_admin_can_update_published_question_and_student_sees_the_new_content(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = $this->studentUser();
        $question = $this->makePublishedQuestion(isFree: true);

        $this->actingAsStaff($admin)
            ->put(route('admin.questions.update', $question), array_merge($this->payload(), [
                'stem' => 'Bệnh nhân 55 tuổi đau ngực, khó thở tăng dần. Chẩn đoán nào phù hợp nhất?',
            ]))
            ->assertRedirect();

        $updated = $question->fresh(['options', 'topic']);
        $this->assertSame(QuestionStatus::Published, $updated->status);
        $this->assertSame(
            'Bệnh nhân 55 tuổi đau ngực, khó thở tăng dần. Chẩn đoán nào phù hợp nhất?',
            strip_tags($updated->stem),
        );

        $this->actingAs($student)
            ->get(route('qbank.index', ['q' => 'khó thở']))
            ->assertOk()
            ->assertSee('Tìm trong ngân hàng câu hỏi')
            ->assertSee('<mark>khó thở</mark>', false);
    }

    public function test_admin_can_turn_free_question_into_premium_and_student_cannot_see_it(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $student = $this->studentUser();
        $question = $this->makePublishedQuestion(isFree: true);

        $this->actingAsStaff($admin)
            ->put(route('admin.questions.update', $question), array_merge($this->payload(), [
                'is_free' => '0',
            ]))
            ->assertRedirect();

        $updated = $question->fresh();
        $this->assertFalse($updated->is_free);

        $this->actingAs($student)
            ->get(route('qbank.index', ['q' => 'đau ngực']))
            ->assertOk()
            ->assertSee('Không tìm thấy câu hỏi phù hợp')
            ->assertDontSee('Bệnh nhân 55 tuổi đau ngực', false);
    }

    public function test_editor_can_upload_question_image_and_save_it_with_question(): void
    {
        Storage::fake('public');

        $editor = $this->staffUser(Role::ContentEditor);
        $image = UploadedFile::fake()->image('stem.png', 800, 600);

        $upload = $this->actingAsStaff($editor)
            ->postJson(route('admin.editor.images'), ['image' => $image])
            ->assertOk();

        $path = (string) $upload->json('path');
        $this->assertNotSame('', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), array_merge($this->payload(), [
                'stem_image_path' => $path,
            ]))
            ->assertRedirect();

        $question = Question::query()->latest('id')->firstOrFail();
        $this->assertSame($path, $question->stem_image_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'stem' => 'Bệnh nhân 55 tuổi đau ngực. Chẩn đoán nào phù hợp nhất?',
            'key_info' => "đau ngực\nChẩn đoán nào phù hợp nhất?",
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

    private function makePublishedQuestion(bool $isFree = true): Question
    {
        $question = Question::factory()->create([
            'topic_id' => $this->topic->id,
            'stem' => 'Bệnh nhân 55 tuổi đau ngực. Chẩn đoán nào phù hợp nhất?',
            'key_info' => ['đau ngực', 'Chẩn đoán nào phù hợp nhất?'],
            'explanation' => 'Giải thích lâm sàng đầy đủ.',
            'attending_tip' => 'Nhớ ECG sớm.',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::Published,
            'is_free' => $isFree,
        ]);

        foreach ([
            ['ACS', true],
            ['GERD', false],
            ['Lo lắng', false],
            ['Viêm phổi', false],
        ] as $i => [$content, $correct]) {
            $question->options()->create([
                'label' => chr(65 + $i),
                'content' => $content,
                'is_correct' => $correct,
                'order' => $i + 1,
            ]);
        }

        return $question->fresh(['options', 'topic']);
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

    private function studentUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Student->value);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }
}
