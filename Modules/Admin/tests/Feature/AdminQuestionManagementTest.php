<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewRequest;
use Modules\QuestionBank\Models\QuestionVersion;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class AdminQuestionManagementTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private MedicalTaxonomyNode $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->topic = $this->makeMedicalNode([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-admin-test',
            'node_type' => 'specialty',
            'sort_order' => 1,
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
        $this->assertSame($editor->id, $question->created_by);
        $this->assertDatabaseHas('question_review_requests', [
            'question_id' => $question->id,
            'action' => QuestionReviewAction::Create->value,
            'status' => QuestionReviewStatus::Pending->value,
            'requested_by' => $editor->id,
        ]);
        $this->assertCount(4, $question->options);
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.question.create']);
    }

    public function test_editor_can_assign_multiple_topics_to_question(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $secondaryTopic = $this->makeMedicalNode([
            'name' => 'Chẩn đoán hình ảnh',
            'slug' => 'chan-doan-hinh-anh-admin-test',
            'node_type' => 'specialty',
            'sort_order' => 2,
        ]);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), array_merge($this->payload(), [
                'medical_taxonomy_node_ids' => [$this->topic->id, $secondaryTopic->id],
            ]))
            ->assertRedirect();

        $question = Question::query()->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$this->topic->id, $secondaryTopic->id],
            $question->medicalTaxonomyNodes()->pluck('medical_taxonomy_nodes.id')->all(),
        );

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.index', ['medical_taxonomy_node_id' => $secondaryTopic->id]))
            ->assertOk()
            ->assertSee('Bệnh nhân 55 tuổi đau ngực', false)
            ->assertSee('Chẩn đoán hình ảnh');

        $this->actingAsStaff($editor)
            ->put(route('admin.questions.update', $question), array_merge($this->payload(), [
                'medical_taxonomy_node_ids' => [$secondaryTopic->id],
            ]))
            ->assertRedirect();

        $question->refresh();
        $this->assertSame(
            [$secondaryTopic->id],
            $question->medicalTaxonomyNodes()->pluck('medical_taxonomy_nodes.id')->all(),
        );
    }

    public function test_question_form_uses_option_explanations_without_general_explanation_field(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.create'))
            ->assertOk()
            ->assertDontSee('name="explanation"', false)
            ->assertDontSee('Giải thích chung')
            ->assertSee('Kiến thức / Gợi ý')
            ->assertDontSee('Ý chính cần gạch chân');

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), $this->payload())
            ->assertRedirect();

        $reviewRequest = QuestionReviewRequest::query()->firstOrFail();

        $this->actingAsStaff($admin)
            ->get(route('admin.questions.reviews.show', $reviewRequest))
            ->assertOk()
            ->assertDontSee('Giải thích lâm sàng đầy đủ.')
            ->assertSee('Nhớ ECG sớm.')
            ->assertSee('đau ngực')
            ->assertSee('Giải thích:')
            ->assertSee('Đúng');
    }

    public function test_question_form_keeps_entered_options_after_validation_error(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $payload = array_merge($this->payload(), [
            'medical_taxonomy_node_ids' => [],
            'options' => [
                ['content' => 'Option typed A', 'is_correct' => '0', 'explanation' => 'Explanation typed A'],
                ['content' => 'Option typed B', 'is_correct' => '1', 'explanation' => 'Explanation typed B'],
                ['content' => 'Option typed C', 'is_correct' => '0'],
            ],
        ]);

        $this->actingAsStaff($editor)
            ->from(route('admin.questions.create'))
            ->post(route('admin.questions.store'), $payload)
            ->assertRedirect(route('admin.questions.create'))
            ->assertSessionHasErrors('medical_taxonomy_node_ids');

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.create'))
            ->assertOk()
            ->assertSee('Option typed A')
            ->assertSee('Option typed B')
            ->assertSee('Option typed C')
            ->assertSee('Explanation typed A')
            ->assertSee('Explanation typed B');
    }

    public function test_admin_can_approve_legacy_question_using_correct_option_explanation(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $admin = $this->staffUser(Role::Admin);
        $payload = $this->payload();
        unset($payload['explanation']);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), $payload)
            ->assertRedirect();

        $question = Question::query()->firstOrFail();
        $this->assertSame('Đúng', strip_tags((string) $question->explanation));

        // Simulate a request created before the general-explanation field existed.
        $question->forceFill(['explanation' => null])->save();
        $reviewRequest = QuestionReviewRequest::query()->firstOrFail();

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.reviews.approve', $reviewRequest))
            ->assertRedirect(route('admin.questions.edit', $question));

        $question->refresh();
        $this->assertSame(QuestionStatus::Published, $question->status);
        $this->assertSame('Đúng', strip_tags((string) $question->explanation));
    }

    public function test_editor_version_history_shows_full_content_without_admin_approval_version(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), array_merge($this->payload(), [
                'stem_image_path' => 'questions/stem-version.png',
            ]))
            ->assertRedirect();

        $question = Question::query()->firstOrFail();
        $reviewRequest = QuestionReviewRequest::query()->firstOrFail();

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.reviews.approve', $reviewRequest))
            ->assertRedirect(route('admin.questions.edit', $question));

        $question->refresh();
        $this->assertSame(2, $question->version);
        $this->assertDatabaseHas('question_versions', [
            'question_id' => $question->id,
            'version' => 2,
            'event' => 'status',
        ]);

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.versions.index', $question))
            ->assertOk()
            ->assertSee('Hiện tại: phiên bản 1')
            ->assertSee('Hình ảnh')
            ->assertSee('/storage/questions/stem-version.png')
            ->assertSee('Kiến thức / Gợi ý')
            ->assertSee('Nhớ ECG sớm.')
            ->assertSee('Đáp án đúng')
            ->assertSee('Giải thích đáp án:')
            ->assertSee('Đúng')
            ->assertDontSee('Phiên bản 2')
            ->assertDontSee('Đổi trạng thái');
    }

    public function test_editor_can_view_history_and_restore_an_old_question_version(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.store'), $this->payload())
            ->assertRedirect();

        $question = Question::query()->firstOrFail();
        $originalVersion = $question->version;
        $this->assertDatabaseHas('question_versions', [
            'question_id' => $question->id,
            'version' => $originalVersion,
            'event' => 'save',
        ]);

        $this->actingAsStaff($editor)
            ->put(route('admin.questions.update', $question), array_merge($this->payload(), [
                'stem' => 'Nội dung đã chỉnh sửa ở phiên bản mới.',
                'options' => [
                    ['content' => 'Đáp án mới đúng', 'is_correct' => '1'],
                    ['content' => 'Đáp án mới sai', 'is_correct' => '0'],
                ],
            ]))
            ->assertRedirect();

        $question->refresh();
        $this->assertSame($originalVersion + 1, $question->version);

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.versions.index', $question))
            ->assertOk()
            ->assertSee("Phiên bản {$originalVersion}")
            ->assertSee("Phiên bản {$question->version}")
            ->assertSee('Khôi phục');

        $oldVersion = QuestionVersion::query()
            ->where('question_id', $question->id)
            ->where('version', $originalVersion)
            ->firstOrFail();

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.versions.restore', [$question, $oldVersion]))
            ->assertRedirect(route('admin.questions.edit', $question));

        $restored = $question->fresh(['options', 'medicalTaxonomyNodes']);
        $this->assertSame($originalVersion + 2, $restored->version);
        $this->assertSame(QuestionStatus::Draft, $restored->status);
        $this->assertSame(
            'Bệnh nhân 55 tuổi đau ngực. Chẩn đoán nào phù hợp nhất?',
            strip_tags($restored->stem),
        );
        $this->assertCount(4, $restored->options);
        $this->assertDatabaseHas('question_versions', [
            'question_id' => $question->id,
            'version' => $restored->version,
            'event' => 'restore',
            'restored_from_version' => $originalVersion,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.question.version_restore',
            'auditable_id' => $question->id,
        ]);
    }

    public function test_editor_can_submit_for_review_but_cannot_publish(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $question = $this->makeDraftQuestion($editor);

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

        $question->refresh();
        $this->assertSame(QuestionStatus::Published, $question->status);
        $this->assertSame($admin->id, $question->reviewer_id);
        $this->assertGreaterThan(0, $question->version);
        $this->assertDatabaseHas('question_versions', [
            'question_id' => $question->id,
            'version' => $question->version,
            'event' => 'status',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.question.status_change']);
    }

    public function test_admin_can_reject_question_in_review(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makeDraftQuestion();
        $question->forceFill(['status' => QuestionStatus::InReview])->save();

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Rejected->value,
                'rejection_reason' => 'Thiếu giải thích cho đáp án sai.',
            ])
            ->assertRedirect();

        $question->refresh();
        $this->assertSame(QuestionStatus::Rejected, $question->status);
        $this->assertSame($admin->id, $question->reviewer_id);
        $this->assertSame('Thiếu giải thích cho đáp án sai.', $question->rejection_reason);
    }

    public function test_rejected_question_cannot_be_edited_until_back_to_draft(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makeDraftQuestion();
        $question->forceFill([
            'status' => QuestionStatus::Rejected,
            'rejection_reason' => 'Cần bổ sung guideline.',
        ])->save();

        $this->actingAsStaff($admin)
            ->put(route('admin.questions.update', $question), $this->payload())
            ->assertSessionHasErrors('status');

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.transition', $question), [
                'status' => QuestionStatus::Draft->value,
            ])
            ->assertRedirect();

        $this->actingAsStaff($admin)
            ->put(route('admin.questions.update', $question), array_merge($this->payload(), [
                'stem' => 'Nội dung đã sửa sau khi từ chối.',
            ]))
            ->assertRedirect();

        $this->assertSame('Nội dung đã sửa sau khi từ chối.', strip_tags($question->fresh()->stem));
    }

    public function test_admin_can_clone_question(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makePublishedQuestion();

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.clone', $question))
            ->assertRedirect();

        $clone = Question::query()->where('cloned_from_id', $question->id)->firstOrFail();
        $this->assertSame(QuestionStatus::Draft, $clone->status);
        $this->assertSame($question->version, $clone->cloned_from_version);
        $this->assertSame($admin->id, $clone->created_by);
        $this->assertCount(4, $clone->options);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.question.clone',
            'auditable_id' => $clone->id,
        ]);
    }

    public function test_content_creator_only_sees_and_opens_own_questions(): void
    {
        $creatorA = $this->staffUser(Role::ContentEditor);
        $creatorB = $this->staffUser(Role::ContentEditor);
        $ownQuestion = $this->makePublishedQuestion(createdBy: $creatorA);
        $foreignQuestion = $this->makePublishedQuestion(createdBy: $creatorB, stem: 'Câu hỏi bí mật của creator B');

        $this->actingAsStaff($creatorA)
            ->get(route('admin.questions.index'))
            ->assertOk()
            ->assertSee(strip_tags($ownQuestion->stem), false)
            ->assertDontSee('Câu hỏi bí mật của creator B', false);

        $this->actingAsStaff($creatorA)
            ->get(route('admin.questions.edit', $foreignQuestion))
            ->assertNotFound();

        $admin = $this->staffUser(Role::Admin);
        $this->actingAsStaff($admin)
            ->get(route('admin.questions.index'))
            ->assertOk()
            ->assertSee('Câu hỏi bí mật của creator B', false);
    }

    public function test_creator_update_is_not_applied_until_admin_approves(): void
    {
        $creator = $this->staffUser(Role::ContentEditor);
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makePublishedQuestion(createdBy: $creator);
        $originalStem = strip_tags($question->stem);

        $this->actingAsStaff($creator)
            ->put(route('admin.questions.update', $question), array_merge($this->payload(), [
                'stem' => 'Nội dung mới phải chờ admin duyệt.',
            ]))
            ->assertRedirect();

        $this->assertSame($originalStem, strip_tags($question->fresh()->stem));
        $reviewRequest = QuestionReviewRequest::query()->where('question_id', $question->id)->firstOrFail();
        $this->assertSame(QuestionReviewAction::Update, $reviewRequest->action);
        $this->assertSame(QuestionReviewStatus::Pending, $reviewRequest->status);

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.reviews.approve', $reviewRequest))
            ->assertRedirect(route('admin.questions.edit', $question));

        $this->assertSame('Nội dung mới phải chờ admin duyệt.', strip_tags($question->fresh()->stem));
        $this->assertSame(QuestionReviewStatus::Approved, $reviewRequest->fresh()->status);
    }

    public function test_creator_delete_is_soft_deleted_only_after_admin_approves(): void
    {
        $creator = $this->staffUser(Role::ContentEditor);
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makePublishedQuestion(createdBy: $creator);

        $this->actingAsStaff($creator)
            ->delete(route('admin.questions.destroy', $question))
            ->assertRedirect(route('admin.questions.index'));

        $this->assertNotSoftDeleted('questions', ['id' => $question->id]);
        $reviewRequest = QuestionReviewRequest::query()->where('question_id', $question->id)->firstOrFail();
        $this->assertSame(QuestionReviewAction::Delete, $reviewRequest->action);

        $this->actingAsStaff($admin)
            ->post(route('admin.questions.reviews.approve', $reviewRequest))
            ->assertRedirect(route('admin.questions.index'));

        $this->assertSoftDeleted('questions', ['id' => $question->id]);
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

    public function test_admin_can_view_question_stats_page(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $question = $this->makePublishedQuestion();
        $question->forceFill([
            'stats_cache' => [
                'total_attempts' => 40,
                'study_mode_attempts' => 25,
                'exam_mode_attempts' => 15,
                'correct_attempts' => 28,
                'incorrect_attempts' => 12,
                'correct_rate' => 0.7,
                'total_reports' => 2,
                'reports_by_reason' => ['wrong_answer' => 1, 'unclear' => 1],
            ],
            'stats_updated_at' => now(),
        ])->save();

        $this->actingAsStaff($admin)
            ->get(route('admin.questions.stats', $question))
            ->assertOk()
            ->assertSee('Thống kê câu hỏi', false)
            ->assertSee('70.0%', false)
            ->assertSee('Study mode', false)
            ->assertSee('wrong_answer', false);
    }

    public function test_content_creator_cannot_view_foreign_question_stats(): void
    {
        $creatorA = $this->staffUser(Role::ContentEditor);
        $creatorB = $this->staffUser(Role::ContentEditor);
        $foreignQuestion = $this->makePublishedQuestion(createdBy: $creatorB);

        $this->actingAsStaff($creatorA)
            ->get(route('admin.questions.stats', $foreignQuestion))
            ->assertNotFound();
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

        $published = $question->fresh(['options', 'medicalTaxonomyNodes']);
        $this->assertSame(QuestionStatus::Published, $published->status);
        $this->assertSame('Bệnh nhân 55 tuổi đau ngực. Chẩn đoán nào phù hợp nhất?', strip_tags($published->stem));
        $this->assertSame(['đau ngực', 'Chẩn đoán nào phù hợp nhất?'], array_values(array_map('strip_tags', $published->key_info ?? [])));
        $this->assertSame('Đúng', strip_tags($published->explanation));
        $this->assertSame('Nhớ ECG sớm.', strip_tags($published->attending_tip));
        $this->assertSame(Difficulty::Medium, $published->difficulty);
        $this->assertSame([$this->topic->id], $published->medicalTaxonomyNodes->pluck('id')->all());
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

        $updated = $question->fresh(['options', 'medicalTaxonomyNodes']);
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
            'medical_taxonomy_node_ids' => [$this->topic->id],
            'is_free' => '1',
            'options' => [
                ['content' => 'ACS', 'is_correct' => '1', 'explanation' => 'Đúng'],
                ['content' => 'GERD', 'is_correct' => '0', 'explanation' => 'Sai'],
                ['content' => 'Lo lắng', 'is_correct' => '0'],
                ['content' => 'Viêm phổi', 'is_correct' => '0'],
            ],
        ];
    }

    private function makeDraftQuestion(?User $createdBy = null): Question
    {
        $question = Question::factory()->draft()->create([
            'stem' => 'Stem draft test',
            'explanation' => 'Explanation draft',
            'difficulty' => Difficulty::Easy,
            'created_by' => $createdBy?->id,
        ]);
        $question->medicalTaxonomyNodes()->sync([$this->topic->id]);

        foreach (['A đúng', 'B', 'C', 'D'] as $i => $content) {
            $question->options()->create([
                'label' => chr(65 + $i),
                'content' => $content,
                'is_correct' => $i === 0,
                'order' => $i + 1,
            ]);
        }

        return $question->fresh(['options', 'medicalTaxonomyNodes']);
    }

    private function makePublishedQuestion(
        bool $isFree = true,
        ?User $createdBy = null,
        string $stem = 'Bệnh nhân 55 tuổi đau ngực. Chẩn đoán nào phù hợp nhất?',
    ): Question {
        $question = Question::factory()->create([
            'stem' => $stem,
            'key_info' => ['đau ngực', 'Chẩn đoán nào phù hợp nhất?'],
            'explanation' => 'Giải thích lâm sàng đầy đủ.',
            'attending_tip' => 'Nhớ ECG sớm.',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::Published,
            'is_free' => $isFree,
            'created_by' => $createdBy?->id,
        ]);
        $question->medicalTaxonomyNodes()->sync([$this->topic->id]);

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

        return $question->fresh(['options', 'medicalTaxonomyNodes']);
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
