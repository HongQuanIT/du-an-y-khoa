<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Personalization\Models\BookmarkFolder;
use Modules\Personalization\Models\BookmarkFolderItem;
use Modules\QuestionBank\Actions\RepeatQuestionSessionAction;
use Modules\QuestionBank\Database\Seeders\DemoLearningSeeder;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionScopeType;
use Modules\QuestionBank\Enums\QuestionStatus as PublicationStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionScope;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionSessionSnapshot;
use Modules\QuestionBank\Models\QuestionStatus;
use Modules\QuestionBank\Services\QuestionKeyInfoRenderer;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class QuestionBankFlowTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private MedicalTaxonomyNode $topic;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::findOrCreate(Role::Student->value, 'web');
        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);
        $this->topic = $this->makeMedicalNode([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-qbank-test',
            'node_type' => 'system',
            'sort_order' => 1,
        ]);
    }

    public function test_builder_uses_real_taxonomy_and_returns_a_live_count(): void
    {
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu miễn phí 1');
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu miễn phí 2');
        $this->createQuestion($this->topic, false, Difficulty::Easy, 'Câu premium');

        $builderResponse = $this->actingAs($this->user)
            ->get(route('qbank.create'))
            ->assertOk()
            ->assertSee('Tim mạch')
            ->assertSeeInOrder([
                'Tạo phiên luyện tập bằng chế độ AI',
                'Thiết lập chủ đề',
                'Tiêu chí phiên luyện',
                'Chế độ học tập',
                'Bắt đầu',
            ])
            ->assertSee('Bác sĩ nội trú')
            ->assertSee('USMLE Step 2 CK')
            ->assertSee('NBME')
            ->assertSee('ABCDE approach')
            ->assertSee('Acute coronary syndromes')
            ->assertSee('Stroke')
            ->assertSee('Đau ngực')
            ->assertSee('Khó thở')
            ->assertSee('Ngất')
            ->assertSee('Rất dễ')
            ->assertSee('Rất khó')
            ->assertSee('name="difficulties[]"', false)
            ->assertSee('1 phút 30 giây mỗi câu')
            ->assertSee(':disabled="matching === 0"', false)
            ->assertSee(':max="Math.max(1, questionLimit())"', false)
            ->assertSee('@input="countTouched = true; clampQuestionCount()"', false)
            ->assertDontSee('name="time_limit_minutes"', false)
            ->assertDontSeeText('const difficulty = form.querySelector');

        $this->assertStringContainsString(
            'mode: &#039;study&#039;',
            $builderResponse->getContent(),
        );

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), $this->sessionPayload(count: 10))
            ->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_session_size_can_equal_the_full_matching_pool(): void
    {
        for ($index = 1; $index <= 25; $index++) {
            $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu miễn phí '.$index);
        }

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), $this->sessionPayload(count: 25))
            ->assertOk()
            ->assertJsonPath('data.count', 25);

        $this->actingAs($this->user)
            ->post(route('qbank.store'), $this->sessionPayload(count: 25))
            ->assertRedirect();

        $session = QuestionSession::firstOrFail();
        $this->assertSame(25, $session->total);
        $this->assertSame(25, $session->filters['count']);
    }

    public function test_can_count_and_create_session_for_specific_folder(): void
    {
        $folder = BookmarkFolder::query()->create([
            'user_id' => $this->user->id,
            'name' => 'Bộ sưu tập đặc biệt',
        ]);
        $question1 = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu 1');
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu 2');

        BookmarkFolderItem::query()->create([
            'folder_id' => $folder->id,
            'question_id' => (string) $question1->id,
        ]);

        $payload = array_merge($this->sessionPayload(count: 10), [
            'folder_id' => $folder->id,
            'saved_only' => 1,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), $payload)
            ->assertOk()
            ->assertJsonPath('data.count', 1);

        $this->actingAs($this->user)
            ->post(route('qbank.store'), $payload)
            ->assertRedirect();

        $session = QuestionSession::latest('id')->firstOrFail();
        $this->assertSame(1, $session->total);
    }

    public function test_key_info_derives_clinical_clues_for_legacy_questions(): void
    {
        $stem = '[Amboss] Ca lâm sàng #064 – Skin & Subcutaneous Tissue. '
            .'Viêm khớp gối nóng đỏ, dịch đục, sốt. '
            .'Xét nghiệm dịch khớp ưu tiên để loại trừ?';
        $renderer = app(QuestionKeyInfoRenderer::class);
        $phrases = $renderer->resolvePhrases($stem, []);

        $this->assertSame(['Viêm khớp gối nóng đỏ, dịch đục, sốt.'], $phrases);
        $this->assertStringContainsString(
            '<span data-key-info class="underline decoration-amber-600 decoration-2 underline-offset-2">'
                .'Viêm khớp gối nóng đỏ, dịch đục, sốt.</span>',
            $renderer->render($stem, $phrases),
        );
    }

    public function test_custom_scope_filters_are_real_hard_boundaries_and_preserve_free_gating(): void
    {
        $targetA = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Target A');
        $targetB = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Target B');
        $premium = $this->createQuestion($this->topic, false, Difficulty::Easy, 'Premium target');
        $wrongExam = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Wrong exam');
        $wrongArticle = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Wrong article');
        $wrongSymptom = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Wrong symptom');

        $this->assignScopes($targetA, ['usmle-step-2-ck'], ['abcde-approach'], ['chest-pain']);
        $this->assignScopes($targetB, ['usmle-step-2-ck'], ['sepsis'], ['dyspnea']);
        $this->assignScopes($premium, ['usmle-step-2-ck'], ['sepsis'], ['dyspnea']);
        $this->assignScopes($wrongExam, ['nbme'], ['sepsis'], ['dyspnea']);
        $this->assignScopes($wrongArticle, ['usmle-step-2-ck'], ['stroke'], ['dyspnea']);
        $this->assignScopes($wrongSymptom, ['usmle-step-2-ck'], ['sepsis'], ['fever']);
        $outsideTopic = $this->makeMedicalNode([
            'name' => 'Hô hấp',
            'slug' => 'ho-hap-qbank-test',
            'node_type' => 'system',
            'sort_order' => 2,
        ]);
        $outside = $this->createQuestion($outsideTopic, true, Difficulty::Easy, 'Outside A');
        $this->assignScopes($outside, ['usmle-step-2-ck'], ['sepsis'], ['dyspnea']);

        $payload = $this->sessionPayload(count: 10);
        $payload['exam_key'] = 'usmle-step-2-ck';
        $payload['articles'] = ['abcde-approach', 'sepsis'];
        $payload['symptoms'] = ['chest-pain', 'dyspnea'];

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), $payload)
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->actingAs($this->user)
            ->post(route('qbank.store'), $payload)
            ->assertRedirect();

        $session = QuestionSession::firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$targetA->getKey(), $targetB->getKey()],
            $session->question_ids,
        );
        $this->assertSame([$this->topic->id], $session->filters['medical_taxonomy_node_ids']);
        $this->assertSame('usmle-step-2-ck', $session->filters['exam_key']);
        $this->assertSame(['abcde-approach', 'sepsis'], $session->filters['articles']);
        $this->assertSame(['chest-pain', 'dyspnea'], $session->filters['symptoms']);
        $this->assertSame(2, $session->total);
        $this->assertSame(2, $session->filters['count']);
    }

    public function test_builder_combines_multiple_difficulty_levels(): void
    {
        $veryEasy = $this->createQuestion($this->topic, true, Difficulty::VeryEasy, 'Very easy target');
        $this->createQuestion($this->topic, true, Difficulty::Medium, 'Medium excluded');
        $veryHard = $this->createQuestion($this->topic, true, Difficulty::VeryHard, 'Very hard target');
        $payload = $this->sessionPayload(count: 10);
        unset($payload['difficulty']);
        $payload['difficulties'] = [Difficulty::VeryEasy->value, Difficulty::VeryHard->value];

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), $payload)
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->actingAs($this->user)
            ->post(route('qbank.store'), $payload)
            ->assertRedirect();

        $session = QuestionSession::firstOrFail();
        $this->assertEqualsCanonicalizing([$veryEasy->getKey(), $veryHard->getKey()], $session->question_ids);
        $this->assertSame(
            [Difficulty::VeryEasy->value, Difficulty::VeryHard->value],
            $session->filters['difficulties'],
        );
    }

    public function test_question_api_does_not_expose_premium_stems_to_free_users(): void
    {
        $free = $this->createQuestion($this->topic, true, Difficulty::Easy, 'API free stem');
        $premium = $this->createQuestion($this->topic, false, Difficulty::Easy, 'API premium stem');

        $this->actingAs($this->user)
            ->getJson(route('api.question-bank.questions.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $free->getKey())
            ->assertJsonMissing(['stem' => $premium->stem]);

        $this->actingAs($this->user)
            ->getJson(route('api.question-bank.questions.show', $premium))
            ->assertForbidden();
    }

    public function test_study_session_runs_from_create_through_summary_and_review(): void
    {
        $first = $this->createQuestion($this->topic, true, Difficulty::Medium, 'Study first');
        $second = $this->createQuestion($this->topic, true, Difficulty::Medium, 'Study second');
        $first->update([
            'key_info' => ['Study first'],
            'attending_tip' => 'Gợi ý dành cho câu hỏi đầu tiên.',
        ]);
        $second->update([
            'key_info' => ['Study second'],
            'attending_tip' => 'Gợi ý dành cho câu hỏi thứ hai.',
        ]);

        $this->actingAs($this->user)
            ->post(route('qbank.store'), $this->sessionPayload(count: 2, difficulty: Difficulty::Medium))
            ->assertRedirect();

        $session = QuestionSession::firstOrFail();

        $this->actingAs($this->user)
            ->get(route('qbank.session', $session))
            ->assertOk()
            ->assertViewIs('studyplan::session')
            ->assertSee('Phiên học tập')
            ->assertSee('Navigator')
            ->assertSee('Lưu câu hỏi')
            ->assertSee('Kiến thức')
            ->assertSee('Gợi ý')
            ->assertSee('data-testid="question-knowledge-toolbar"', false)
            ->assertSee("window.addEventListener('popstate'", false)
            ->assertSee('installBrowserExitGuard()', false)
            ->assertSee('data-testid="attending-tip-toggle"', false)
            ->assertSee('data-testid="attending-tip-panel"', false)
            ->assertSee('data-testid="attending-tip-used-badge"', false)
            ->assertSeeInOrder(['Ghi chú', 'Nghiên cứu'])
            ->assertSee('data-testid="research-reference-toggle"', false)
            ->assertSee('data-testid="research-reference-panel"', false)
            ->assertSee('data-testid="research-lab-values-table"', false)
            ->assertSee('data-testid="research-lab-tab-serum"', false)
            ->assertSee('data-testid="research-lab-tab-cerebrospinal"', false)
            ->assertSee('data-testid="research-lab-tab-blood"', false)
            ->assertSee('data-testid="research-lab-tab-urine_bmi"', false)
            ->assertSee('data-testid="question-answer-pane"', false)
            ->assertSee('Câu hỏi – câu trả lời')
            ->assertSee('Lab Values')
            ->assertSee('Reference Range')
            ->assertSee('SI Reference')
            ->assertDontSee('data-testid="lab-reference-toggle"', false)
            ->assertDontSee('data-testid="lab-reference-panel"', false)
            ->assertSee('Alanine aminotransferase (ALT)')
            ->assertSee('Body Mass Index (BMI)')
            ->assertSee('data-testid="key-info-used-badge"', false)
            ->assertSee('Đã dùng kiến thức')
            ->assertSee('Đã dùng gợi ý')
            ->assertSee('data-key-info', false)
            ->assertSee('Ghi chú')
            ->assertSee('Tô màu văn bản')
            ->assertSee('Chọn một đáp án để xem giải thích');

        $this->actingAs($this->user)
            ->postJson(route('qbank.session.annotate', $session), [
                'question_id' => $first->getKey(),
                'key_info_used' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.key_info_used', true);

        $this->actingAs($this->user)
            ->postJson(route('qbank.session.annotate', $session), [
                'question_id' => $second->getKey(),
                'attending_tip_used' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.attending_tip_used', true);

        $this->actingAs($this->user)
            ->get(route('qbank.session', $session))
            ->assertOk()
            ->assertSee('keyInfoUsed: false', false)
            ->assertSee('attendingTipUsed: false', false);

        foreach ($session->question_ids as $index => $questionId) {
            $question = Question::with('options')->findOrFail($questionId);
            $option = $index === 0
                ? $question->options->firstWhere('is_correct', true)
                : $question->options->firstWhere('is_correct', false);

            $response = $this->actingAs($this->user)
                ->post(route('qbank.session.answer', $session), [
                    'question_id' => $questionId,
                    'option_ids' => [$option->id],
                    'index' => $index,
                    'time_spent_seconds' => 30,
                ]);

            if ($index === 0) {
                $response->assertRedirect(route('qbank.session', [$session, 'index' => 0]));

                $this->actingAs($this->user)
                    ->get(route('qbank.session', [$session, 'index' => 0]))
                    ->assertOk()
                    ->assertSee('keyInfoEnabled: true', false)
                    ->assertSee('attendingTipOpen: true', false);
            }
        }

        $this->assertTrue((bool) QuestionAttempt::query()
            ->where('session_id', $session->getKey())
            ->where('question_id', $first->getKey())
            ->value('used_hint'));
        $this->assertTrue((bool) QuestionAttempt::query()
            ->where('session_id', $session->getKey())
            ->where('question_id', $second->getKey())
            ->value('used_hint'));
        $this->assertDatabaseMissing('audit_logs', [
            'actor_id' => $this->user->getKey(),
            'action' => AuditAction::LearningQuestionAnswered->value,
        ]);

        $this->assertSame(SessionStatus::Active, $session->refresh()->status);
        $this->assertSame(2, $session->answered_count);
        $this->assertSame(1, $session->correct_count);
        $this->assertSame(2, QuestionAttempt::where('session_id', $session->getKey())->count());

        $this->actingAs($this->user)
            ->get(route('qbank.session', [$session, 'index' => 1]))
            ->assertOk()
            ->assertSee('Giải thích');

        $this->actingAs($this->user)
            ->post(route('qbank.session.finish', $session))
            ->assertRedirect(route('qbank.summary', $session));
        $this->assertSame(SessionStatus::Completed, $session->refresh()->status);
        $this->assertEqualsCanonicalizing(
            [
                AuditAction::LearningSessionCreated->value,
                AuditAction::LearningSessionCompleted->value,
            ],
            AuditLog::query()
                ->where('actor_id', $this->user->getKey())
                ->where('session_id', (string) $session->getKey())
                ->pluck('action')
                ->all(),
        );

        $peer = User::factory()->create();
        $peerSession = QuestionSession::factory()->create([
            'user_id' => $peer->getKey(),
            'question_ids' => $session->question_ids,
            'total' => 2,
            'answered_count' => 2,
            'correct_count' => 1,
        ]);
        foreach ($session->question_ids as $index => $questionId) {
            QuestionAttempt::factory()->create([
                'session_id' => $peerSession->getKey(),
                'user_id' => $peer->getKey(),
                'question_id' => $questionId,
                'is_correct' => $index !== 0,
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('qbank.summary', $session))
            ->assertOk()
            ->assertViewIs('studyplan::session-summary')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['accuracy'] === 50)
            ->assertViewHas('accuracy', 50)
            ->assertViewHas('questionOverview', function (array $rows): bool {
                return count($rows) === 2
                    && collect($rows)->every(fn (array $row): bool => $row['peer_accuracy'] === 50
                        && $row['peer_users'] === 2
                        && $row['time_spent_seconds'] === 30
                        && $row['difficulty'] === 'Trung bình');
            })
            ->assertSee('Tóm tắt nhanh')
            ->assertSee('Tỷ lệ đúng theo chủ đề')
            ->assertSee('id="student-session-topic-accuracy"', false)
            ->assertSee('Phân tích chi tiết chủ đề')
            ->assertSee('Tổng quan từng câu')
            ->assertSee('Thời gian cho mỗi câu hỏi')
            ->assertSee('Thống kê đồng nghiệp')
            ->assertSee('Khó khăn')
            ->assertSee('data-testid="question-overview-table"', false)
            ->assertSee('Quay lại ngân hàng câu hỏi');

        $this->actingAs($this->user)
            ->get(route('qbank.review', $session))
            ->assertOk()
            ->assertViewHas('items', fn (array $items): bool => count($items) === 2)
            ->assertSee($first->stem)
            ->assertSee($second->stem);
    }

    public function test_study_session_renders_question_image_next_to_stem(): void
    {
        $this->createQuestion(
            $this->topic,
            true,
            Difficulty::Easy,
            'Image stem',
            'question-images/image-stem.png',
        );

        $this->actingAs($this->user)
            ->post(route('qbank.store'), $this->sessionPayload(count: 1, difficulty: Difficulty::Easy))
            ->assertRedirect();

        $session = QuestionSession::firstOrFail();

        $this->actingAs($this->user)
            ->get(route('qbank.session', $session))
            ->assertOk()
            ->assertSee('/storage/question-images/image-stem.png', false)
            ->assertSee('Ảnh minh họa câu hỏi');
    }

    public function test_session_snapshot_preserves_content_grading_and_review_after_question_is_changed_and_deleted(): void
    {
        $question = $this->createQuestion(
            $this->topic,
            true,
            Difficulty::Easy,
            'Nội dung nguyên bản của phiên',
        );
        $correctOption = $question->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('qbank.store'), $this->sessionPayload(count: 1))
            ->assertRedirect();

        $session = QuestionSession::firstOrFail();
        $snapshot = QuestionSessionSnapshot::where('session_id', $session->getKey())->firstOrFail();
        $this->assertSame('Nội dung nguyên bản của phiên', $snapshot->payload['stem']);
        $snapshotCorrectOption = collect($snapshot->payload['options'])
            ->firstWhere('is_correct', true);
        $this->assertSame('Đáp án đúng Nội dung nguyên bản của phiên', $snapshotCorrectOption['content']);

        $question->forceFill([
            'stem' => 'Nội dung đã bị sửa sau khi tạo phiên',
            'explanation' => 'Giải thích đã bị sửa',
        ])->save();
        $correctOption->forceFill(['content' => 'Đáp án đã bị sửa', 'is_correct' => false])->save();
        $question->forceDelete();

        $this->actingAs($this->user)
            ->get(route('qbank.session', $session))
            ->assertOk()
            ->assertSee('Nội dung nguyên bản của phiên')
            ->assertSee('Đáp án đúng Nội dung nguyên bản của phiên')
            ->assertDontSee('Nội dung đã bị sửa sau khi tạo phiên');

        $this->actingAs($this->user)
            ->postJson(route('qbank.session.answer', $session), [
                'question_id' => $question->getKey(),
                'option_ids' => [$correctOption->getKey()],
                'index' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_correct', true);

        $this->assertDatabaseHas('question_attempts', [
            'session_id' => $session->getKey(),
            'question_id' => $question->getKey(),
            'is_correct' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('qbank.session.finish', $session))
            ->assertRedirect(route('qbank.summary', $session));
        $this->actingAs($this->user)
            ->get(route('qbank.summary', $session))
            ->assertOk()
            ->assertViewHas('accuracy', 100);
        $this->actingAs($this->user)
            ->get(route('qbank.review', $session))
            ->assertOk()
            ->assertSee('Nội dung nguyên bản của phiên')
            ->assertSee('Đáp án đúng Nội dung nguyên bản của phiên');

        $repeated = app(RepeatQuestionSessionAction::class)
            ->handle($this->user, $session->refresh(), ['correct'], 1);
        $this->assertSame([$question->getKey()], $repeated->question_ids);
        $this->assertSame(
            'Nội dung nguyên bản của phiên',
            $repeated->snapshots()->firstOrFail()->payload['stem'],
        );
    }

    public function test_qbank_review_displays_question_stem_image(): void
    {
        $question = $this->createQuestion(
            $this->topic,
            true,
            Difficulty::Easy,
            'Câu hỏi có ảnh điện tâm đồ',
            'questions/test-ecg.png',
        );

        $session = QuestionSession::create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'source' => 'custom',
            'question_ids' => [$question->id],
            'total' => 1,
            'answered_count' => 1,
            'correct_count' => 1,
        ]);

        app(\Modules\QuestionBank\Services\QuestionSessionSnapshots::class)->capture($session);

        $this->actingAs($this->user)
            ->get(route('qbank.review', $session))
            ->assertOk()
            ->assertSee('Xem lại câu hỏi')
            ->assertSee('test-ecg.png')
            ->assertSee('imageViewerOpen');
    }

    public function test_question_overview_paginates_after_five_rows(): void
    {
        for ($number = 1; $number <= 6; $number++) {
            $this->createQuestion(
                $this->topic,
                true,
                Difficulty::Easy,
                "Câu phân trang {$number}",
            );
        }

        $this->actingAs($this->user)
            ->post(route('qbank.store'), $this->sessionPayload(count: 6, difficulty: Difficulty::Easy))
            ->assertRedirect();

        $session = QuestionSession::firstOrFail();

        foreach ($session->question_ids as $index => $questionId) {
            $question = Question::with('options')->findOrFail($questionId);
            $this->actingAs($this->user)->post(route('qbank.session.answer', $session), [
                'question_id' => $questionId,
                'option_ids' => [$question->options->firstWhere('is_correct', true)->id],
                'index' => $index,
                'time_spent_seconds' => $index + 1,
            ]);
        }

        $this->actingAs($this->user)
            ->post(route('qbank.session.finish', $session))
            ->assertRedirect(route('qbank.summary', $session));

        $firstPageQuestion = Question::findOrFail($session->question_ids[0]);
        $secondPageQuestion = Question::findOrFail($session->question_ids[5]);

        $this->actingAs($this->user)
            ->get(route('qbank.summary', $session))
            ->assertOk()
            ->assertSee($firstPageQuestion->stem)
            ->assertDontSee($secondPageQuestion->stem)
            ->assertSee('1–5')
            ->assertSee('/ 6 câu')
            ->assertSee('question_page=2', false)
            ->assertSee('data-testid="question-overview-pagination"', false);

        $this->actingAs($this->user)
            ->get(route('qbank.summary', [$session, 'question_page' => 2]))
            ->assertOk()
            ->assertDontSee($firstPageQuestion->stem)
            ->assertSee($secondPageQuestion->stem)
            ->assertSee('6–6')
            ->assertSee('/ 6 câu');
    }

    public function test_exam_answers_are_not_graded_or_completed_until_finish(): void
    {
        $this->createQuestion($this->topic, true, Difficulty::Hard, 'Exam first');
        $this->createQuestion($this->topic, true, Difficulty::Hard, 'Exam second');

        $payload = $this->sessionPayload(count: 2, difficulty: Difficulty::Hard);
        $payload['mode'] = SessionMode::Exam->value;

        $this->actingAs($this->user)->post(route('qbank.store'), $payload)->assertRedirect(route('exam.session', QuestionSession::first()));
        $session = QuestionSession::firstOrFail();
        $this->assertSame(180, $session->time_limit_seconds);
        $firstSessionQuestion = Question::findOrFail($session->question_ids[0]);

        $this->actingAs($this->user)
            ->get(route('exam.session', $session))
            ->assertOk()
            ->assertViewIs('questionbank::exam-session')
            ->assertSeeInOrder([
                'Câu 1/2',
                'Trường hợp lâm sàng',
                'Chọn đáp án đúng nhất',
                'Tiến độ bài thi',
                'Nộp Bài Ngay',
            ])
            ->assertSee('data-testid="exam-calculator-trigger"', false)
            ->assertSee("window.addEventListener('popstate'", false)
            ->assertSee('installBrowserExitGuard()', false)
            ->assertSee('data-testid="exam-calculator"', false)
            ->assertSee('aria-label="Mở ghi chú"', false)
            ->assertSee('Có thể sử dụng bàn phím số')
            ->assertSee("300: 'Còn 5 phút!'", false)
            ->assertSee("240: 'Còn 4 phút!'", false)
            ->assertSee("180: 'Còn 3 phút!'", false)
            ->assertSee("30: 'Còn 30 giây!'", false)
            ->assertSee("15: 'Còn 15 giây!'", false)
            ->assertSee('}, 10000);', false)
            ->assertSee($firstSessionQuestion->stem);

        $this->actingAs($this->user)
            ->get(route('exam.session', [$session, 'index' => 1]))
            ->assertOk()
            ->assertDontSee('Câu tiếp theo')
            ->assertDontSee('Lưu câu trả lời');

        foreach ($session->question_ids as $index => $questionId) {
            $question = Question::with('options')->findOrFail($questionId);
            $correct = $question->options->firstWhere('is_correct', true);
            $answer = [
                'question_id' => $questionId,
                'option_ids' => [$correct->id],
                'index' => $index,
            ];

            if ($index === 0) {
                $this->actingAs($this->user)
                    ->postJson(route('qbank.session.answer', $session), $answer)
                    ->assertOk()
                    ->assertJsonMissingPath('data.is_correct');
            } else {
                $this->actingAs($this->user)
                    ->post(route('qbank.session.answer', $session), $answer)
                    ->assertRedirect(route('exam.session', [$session, 'index' => 1]));
            }
        }

        $this->assertSame(SessionStatus::Active, $session->refresh()->status);
        $this->assertSame(2, $session->answered_count);
        $this->assertSame(0, $session->correct_count);
        $this->assertSame(0, QuestionStatus::where('user_id', $this->user->id)->count());
        $this->assertSame(2, QuestionAttempt::whereNull('is_correct')->count());

        $this->actingAs($this->user)
            ->post(route('qbank.session.finish', $session))
            ->assertRedirect(route('exam.summary', $session));

        $this->assertSame(SessionStatus::Completed, $session->refresh()->status);
        $this->assertSame(2, $session->correct_count);
        $this->assertSame(0, QuestionAttempt::whereNull('is_correct')->count());
        $this->assertSame(2, QuestionStatus::where('status', UserQuestionStatus::Correct)->count());
    }

    public function test_finishing_early_records_omitted_questions(): void
    {
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Finish one');
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Finish two');
        $this->actingAs($this->user)->post(route('qbank.store'), $this->sessionPayload(count: 2));
        $session = QuestionSession::firstOrFail();

        $question = Question::with('options')->findOrFail($session->question_ids[0]);
        $correct = $question->options->firstWhere('is_correct', true);
        $this->actingAs($this->user)->post(route('qbank.session.answer', $session), [
            'question_id' => $question->getKey(),
            'option_ids' => [$correct->id],
            'index' => 0,
        ]);

        $this->actingAs($this->user)->post(route('qbank.session.finish', $session));

        $this->assertSame(
            1,
            QuestionStatus::query()
                ->where('user_id', $this->user->id)
                ->where('status', UserQuestionStatus::Omitted)
                ->count(),
        );

        $this->actingAs($this->user)
            ->get(route('qbank.summary', $session))
            ->assertViewIs('studyplan::session-summary')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['skipped'] === 1)
            ->assertViewHas('skippedCount', 1);
    }

    public function test_duplicate_study_submit_is_rejected_without_double_counting_status(): void
    {
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Duplicate first');
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Duplicate second');
        $this->actingAs($this->user)->post(route('qbank.store'), $this->sessionPayload(count: 2));
        $session = QuestionSession::firstOrFail();
        $question = Question::with('options')->findOrFail($session->question_ids[0]);
        $correct = $question->options->firstWhere('is_correct', true);
        $payload = [
            'question_id' => $question->getKey(),
            'option_ids' => [$correct->id],
            'index' => 0,
        ];

        $this->actingAs($this->user)->post(route('qbank.session.answer', $session), $payload);
        $this->actingAs($this->user)
            ->post(route('qbank.session.answer', $session), $payload)
            ->assertStatus(409);

        $this->assertSame(1, QuestionAttempt::where('session_id', $session->getKey())->count());
        $this->assertSame(1, $session->refresh()->answered_count);
        $this->assertSame(
            1,
            QuestionStatus::where('question_id', $question->getKey())->value('attempts_count'),
        );
    }

    public function test_answer_endpoint_rejects_a_question_or_option_outside_the_session(): void
    {
        $inside = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Inside');
        $outside = $this->createQuestion($this->topic, true, Difficulty::Hard, 'Outside');
        $this->actingAs($this->user)->post(
            route('qbank.store'),
            $this->sessionPayload(count: 1, difficulty: Difficulty::Easy),
        );
        $session = QuestionSession::firstOrFail();

        $this->actingAs($this->user)
            ->post(route('qbank.session.answer', $session), [
                'question_id' => $outside->getKey(),
                'option_ids' => [$outside->options()->first()->id],
                'index' => 0,
            ])
            ->assertStatus(422);

        $this->actingAs($this->user)
            ->post(route('qbank.session.answer', $session), [
                'question_id' => $inside->getKey(),
                'option_ids' => [$outside->options()->first()->id],
                'index' => 0,
            ])
            ->assertStatus(422);

        $this->assertSame(0, QuestionAttempt::count());
    }

    public function test_pause_resume_and_owner_authorization_are_enforced(): void
    {
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Pause question');
        $this->actingAs($this->user)->post(route('qbank.store'), $this->sessionPayload(count: 1));
        $session = QuestionSession::firstOrFail();

        $this->actingAs($this->user)
            ->post(route('qbank.session.pause', $session), ['current_index' => 0])
            ->assertRedirect(route('qbank.index'));
        $this->assertSame(SessionStatus::Paused, $session->refresh()->status);

        $this->actingAs($this->user)->get(route('qbank.session', $session))->assertOk();
        $this->assertSame(SessionStatus::Paused, $session->refresh()->status);
        $this->actingAs($this->user)
            ->post(route('qbank.session.resume', $session))
            ->assertRedirect(route('qbank.session', [$session, 'index' => 0]));
        $this->assertSame(SessionStatus::Active, $session->refresh()->status);

        $intruder = User::factory()->create();
        $intruder->assignRole(Role::Student->value);
        $this->actingAs($intruder)
            ->get(route('qbank.session', $session))
            ->assertForbidden();
    }

    public function test_pausing_an_exam_freezes_the_timer_until_each_resume(): void
    {
        Carbon::setTestNow('2026-08-06 08:00:00');

        try {
            $question = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Paused exam timer');
            $session = QuestionSession::create([
                'user_id' => $this->user->id,
                'mode' => SessionMode::Exam,
                'status' => SessionStatus::Active,
                'source' => 'custom',
                'filters' => [],
                'question_ids' => [$question->getKey()],
                'total' => 1,
                'time_limit_seconds' => 600,
                'paused_state' => [],
            ]);

            Carbon::setTestNow('2026-08-06 08:02:00');
            $this->actingAs($this->user)
                ->post(route('qbank.session.pause', $session), ['current_index' => 0])
                ->assertRedirect(route('qbank.index'));
            $this->assertSame(480, $session->refresh()->paused_state['timer_remaining_seconds']);

            Carbon::setTestNow('2026-08-06 09:02:00');
            $this->actingAs($this->user)
                ->get(route('exam.session', $session))
                ->assertOk()
                ->assertViewHas('remainingSeconds', 480);

            $this->actingAs($this->user)
                ->post(route('qbank.session.resume', $session))
                ->assertRedirect(route('exam.session', [$session, 'index' => 0]));

            Carbon::setTestNow('2026-08-06 09:03:00');
            $this->actingAs($this->user)
                ->get(route('exam.session', $session))
                ->assertOk()
                ->assertViewHas('remainingSeconds', 420);
            $this->actingAs($this->user)
                ->post(route('qbank.session.pause', $session), ['current_index' => 0])
                ->assertRedirect(route('qbank.index'));
            $this->assertSame(420, $session->refresh()->paused_state['timer_remaining_seconds']);

            Carbon::setTestNow('2026-08-06 10:03:00');
            $this->actingAs($this->user)
                ->get(route('exam.session', $session))
                ->assertOk()
                ->assertViewHas('remainingSeconds', 420);

            $this->actingAs($this->user)->post(route('qbank.session.resume', $session));
            Carbon::setTestNow('2026-08-06 10:10:01');
            $this->actingAs($this->user)
                ->get(route('exam.session', $session))
                ->assertRedirect(route('exam.summary', $session));
            $this->assertSame(SessionStatus::Completed, $session->refresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_legacy_paused_exam_uses_its_last_update_as_the_pause_time(): void
    {
        Carbon::setTestNow('2026-08-06 09:00:00');

        try {
            $question = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Legacy paused exam');
            $session = QuestionSession::create([
                'user_id' => $this->user->id,
                'mode' => SessionMode::Exam,
                'status' => SessionStatus::Paused,
                'source' => 'custom',
                'filters' => [],
                'question_ids' => [$question->getKey()],
                'total' => 1,
                'time_limit_seconds' => 600,
                'paused_state' => ['current_index' => 0],
            ]);
            $session->timestamps = false;
            $session->forceFill([
                'created_at' => Carbon::parse('2026-08-06 08:00:00'),
                'updated_at' => Carbon::parse('2026-08-06 08:02:00'),
            ])->saveQuietly();
            $session->timestamps = true;

            $this->actingAs($this->user)
                ->get(route('qbank.session', $session))
                ->assertOk()
                ->assertViewHas('remainingSeconds', 480);

            $this->actingAs($this->user)->post(route('qbank.session.resume', $session));
            Carbon::setTestNow('2026-08-06 09:01:00');
            $this->actingAs($this->user)
                ->get(route('qbank.session', $session))
                ->assertOk()
                ->assertViewHas('remainingSeconds', 420);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_history_stats_and_filters_are_scoped_to_the_signed_in_owner(): void
    {
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'History question');
        $this->actingAs($this->user)->post(route('qbank.store'), $this->sessionPayload(count: 1));

        $intruder = User::factory()->create();
        QuestionSession::create([
            'user_id' => $intruder->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'source' => 'custom',
            'question_ids' => [],
            'total' => 0,
        ]);

        $this->actingAs($this->user)
            ->get(route('qbank.index'))
            ->assertOk()
            ->assertViewHas('sessions', fn ($sessions): bool => $sessions->total() === 1)
            ->assertViewHas('stats', fn (array $stats): bool => $stats['total_sessions'] === 1);

        $this->actingAs($this->user)
            ->get(route('qbank.index', ['status' => SessionStatus::Completed->value]))
            ->assertOk()
            ->assertViewHas('sessions', fn ($sessions): bool => $sessions->total() === 0);
    }

    public function test_history_menu_renames_repeats_selected_results_and_deletes_a_session(): void
    {
        $unanswered = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Repeat unanswered');
        $withHint = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Repeat with hint');
        $incorrect = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Repeat incorrect');
        $correct = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Repeat correct');
        $session = QuestionSession::create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'source' => 'custom',
            'filters' => [],
            'question_ids' => [
                $unanswered->getKey(),
                $withHint->getKey(),
                $incorrect->getKey(),
                $correct->getKey(),
            ],
            'total' => 4,
            'answered_count' => 3,
            'correct_count' => 2,
        ]);
        QuestionAttempt::create([
            'session_id' => $session->getKey(),
            'user_id' => $this->user->id,
            'question_id' => $withHint->getKey(),
            'selected_option_ids' => [],
            'is_correct' => true,
            'used_hint' => true,
        ]);
        QuestionAttempt::create([
            'session_id' => $session->getKey(),
            'user_id' => $this->user->id,
            'question_id' => $incorrect->getKey(),
            'selected_option_ids' => [],
            'is_correct' => false,
        ]);
        QuestionAttempt::create([
            'session_id' => $session->getKey(),
            'user_id' => $this->user->id,
            'question_id' => $correct->getKey(),
            'selected_option_ids' => [],
            'is_correct' => true,
        ]);

        $this->actingAs($this->user)
            ->patch(route('qbank.session.rename', $session), ['name' => 'Phiên tim mạch cần ôn'])
            ->assertRedirect();

        $this->assertSame('Phiên tim mạch cần ôn', $session->refresh()->filters['name']);
        $this->actingAs($this->user)
            ->get(route('qbank.index'))
            ->assertOk()
            ->assertSee('Phiên tim mạch cần ôn')
            ->assertSee('Đặt lại tên')
            ->assertSee('Làm lại')
            ->assertSee('Xoá')
            ->assertSee('Trả lời đúng có gợi ý');

        $repeatResponse = $this->actingAs($this->user)
            ->post(route('qbank.session.repeat', $session), [
                'repeat_statuses' => ['correct_with_hints', 'incorrect'],
                'question_count' => 2,
            ]);

        $repeated = QuestionSession::query()->whereKeyNot($session->getKey())->firstOrFail();
        $repeatResponse->assertRedirect(route('qbank.session', $repeated));
        $this->assertEqualsCanonicalizing(
            [$withHint->getKey(), $incorrect->getKey()],
            $repeated->question_ids,
        );
        $this->assertSame(2, $repeated->total);
        $this->assertSame(SessionMode::Study, $repeated->mode);
        $this->assertNull($repeated->time_limit_seconds);
        $this->assertSame((string) $session->getKey(), $repeated->filters['repeated_from_session_id']);

        $intruder = User::factory()->create();
        $this->actingAs($intruder)
            ->delete(route('qbank.session.destroy', $session))
            ->assertForbidden();

        $this->actingAs($this->user)
            ->delete(route('qbank.session.destroy', $session))
            ->assertRedirect(route('qbank.index'));
        $this->assertSoftDeleted('question_sessions', ['id' => $session->getKey()]);
    }

    public function test_repeating_an_exam_preserves_exam_layout_and_sets_two_minutes_per_question(): void
    {
        $first = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Repeat exam first');
        $second = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Repeat exam second');
        $original = QuestionSession::create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Exam,
            'status' => SessionStatus::Completed,
            'source' => 'custom',
            'filters' => [],
            'question_ids' => [$first->getKey(), $second->getKey()],
            'total' => 2,
            'answered_count' => 2,
            'correct_count' => 0,
            'time_limit_seconds' => 240,
        ]);

        foreach ([$first, $second] as $question) {
            QuestionAttempt::create([
                'session_id' => $original->getKey(),
                'user_id' => $this->user->id,
                'question_id' => $question->getKey(),
                'selected_option_ids' => [],
                'is_correct' => false,
            ]);
        }

        $repeatAction = app(RepeatQuestionSessionAction::class);
        $oneQuestion = $repeatAction->handle($this->user, $original, ['incorrect'], 1);
        $twoQuestions = $repeatAction->handle($this->user, $original, ['incorrect'], 2);

        $this->assertSame(SessionMode::Exam, $oneQuestion->mode);
        $this->assertSame(1, $oneQuestion->total);
        $this->assertSame(120, $oneQuestion->time_limit_seconds);
        $this->assertSame(SessionMode::Exam, $twoQuestions->mode);
        $this->assertSame(2, $twoQuestions->total);
        $this->assertSame(240, $twoQuestions->time_limit_seconds);

        $this->actingAs($this->user)
            ->get(route('qbank.session', $twoQuestions))
            ->assertOk()
            ->assertViewIs('questionbank::exam-session')
            ->assertViewHas(
                'remainingSeconds',
                fn (?int $seconds): bool => $seconds !== null && $seconds >= 235 && $seconds <= 240,
            );

        $this->actingAs($this->user)
            ->get(route('qbank.index'))
            ->assertOk()
            ->assertSee('Làm lại theo chế độ thi')
            ->assertSee('2 phút cho mỗi câu');
    }

    public function test_demo_seed_keeps_200_questions_and_includes_the_long_goodpasture_case(): void
    {
        $this->seed(DemoLearningSeeder::class);

        $this->assertSame(200, Question::count());
        $question = Question::with(['options', 'medicalTaxonomyNodes'])
            ->where('stem', 'like', 'A 24-year-old man comes to the emergency department%')
            ->firstOrFail();
        $options = $question->options->sortBy('order')->values();

        $this->assertSame(Difficulty::VeryHard, $question->difficulty);
        $this->assertTrue(
            $question->medicalTaxonomyNodes->contains(fn ($node): bool => $node->slug === 'urology')
            || $question->medicalTaxonomyNodes->isNotEmpty(),
        );
        $this->assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G'], $options->pluck('label')->all());
        $this->assertSame('Goodpasture syndrome', $options->first()?->content);
        $this->assertTrue((bool) $options->first()?->is_correct);
        $this->assertTrue($options->slice(1)->every(fn (QuestionOption $option): bool => ! $option->is_correct));
        $this->assertSame([
            'blood-tinged sputum',
            'three episodes of blood in his urine',
            'linear deposits of IgG along the glomerular basement membrane',
        ], $question->key_info);
        $this->assertSame(
            'Ho ra máu kết hợp tiểu máu, suy thận và IgG lắng đọng dạng đường thẳng dọc màng đáy cầu thận là bộ dấu hiệu điển hình của bệnh kháng màng đáy cầu thận (hội chứng Goodpasture).',
            $question->attending_tip,
        );

        foreach (QuestionScopeType::cases() as $scopeType) {
            $this->assertSame(
                200,
                QuestionScope::query()
                    ->where('scope_type', $scopeType)
                    ->distinct()
                    ->count('question_id'),
            );
        }
        $this->assertDatabaseHas('question_scopes', [
            'question_id' => $question->getKey(),
            'scope_type' => QuestionScopeType::Exam->value,
            'scope_key' => 'usmle-step-2-ck',
        ]);
        $this->assertDatabaseHas('question_scopes', [
            'question_id' => $question->getKey(),
            'scope_type' => QuestionScopeType::Symptom->value,
            'scope_key' => 'dyspnea',
        ]);
        $scopeCount = QuestionScope::count();

        $this->seed(DemoLearningSeeder::class);

        $this->assertSame(200, Question::count());
        $this->assertSame($scopeCount, QuestionScope::count());
        $this->assertSame(
            1,
            Question::where('stem', 'like', 'A 24-year-old man comes to the emergency department%')->count(),
        );
    }

    /** @return array<string, mixed> */
    private function sessionPayload(
        int $count,
        Difficulty $difficulty = Difficulty::Easy,
    ): array {
        return [
            'mode' => SessionMode::Study->value,
            'source' => 'custom',
            'count' => $count,
            'medical_taxonomy_node_ids' => [$this->topic->id],
            'difficulty' => $difficulty->value,
            'question_status_mode' => 'latest',
            'saved_only' => false,
        ];
    }

    private function createQuestion(
        MedicalTaxonomyNode $topic,
        bool $isFree,
        Difficulty $difficulty,
        string $stem,
        ?string $stemImagePath = null,
    ): Question {
        $question = Question::create([
            'stem' => $stem,
            'stem_image_path' => $stemImagePath,
            'explanation' => 'Giải thích cho '.$stem,
            'difficulty' => $difficulty,
            'status' => PublicationStatus::Published,
            'is_free' => $isFree,
        ]);

        QuestionOption::create([
            'question_id' => $question->getKey(),
            'label' => 'A',
            'content' => 'Đáp án đúng '.$stem,
            'is_correct' => true,
            'order' => 0,
        ]);
        QuestionOption::create([
            'question_id' => $question->getKey(),
            'label' => 'B',
            'content' => 'Đáp án sai '.$stem,
            'is_correct' => false,
            'order' => 1,
        ]);

        $question->medicalTaxonomyNodes()->sync([$topic->id]);

        return $question;
    }

    public function test_empty_active_session_does_not_redirect_loop_with_summary(): void
    {
        $session = QuestionSession::create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Active,
            'source' => 'custom',
            'filters' => ['status' => 'unseen'],
            'question_ids' => [],
            'total' => 0,
            'answered_count' => 3,
            'correct_count' => 0,
            'paused_state' => ['current_index' => 3],
        ]);

        $this->actingAs($this->user)
            ->get(route('qbank.session', $session))
            ->assertRedirect(route('qbank.index'));

        $this->actingAs($this->user)
            ->get(route('qbank.summary', $session))
            ->assertRedirect(route('qbank.index'));

        $this->actingAs($this->user)
            ->get(route('qbank.review', $session))
            ->assertRedirect(route('qbank.index'));
    }

    public function test_student_can_submit_feedback_for_question_knowledge_and_answer(): void
    {
        $question = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu cần phản hồi');
        $option = $question->options()->firstOrFail();
        $session = QuestionSession::query()->create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Active,
            'source' => 'custom',
            'question_ids' => [(string) $question->getKey()],
            'total' => 1,
        ]);

        $this->actingAs($this->user)
            ->get(route('qbank.session', $session))
            ->assertOk()
            ->assertSee('Phản hồi câu hỏi')
            ->assertSee('Phản hồi của bạn có nội dung như thế nào?');

        $this->actingAs($this->user)
            ->postJson(route('qbank.session.feedback', $session), [
                'question_id' => (string) $question->getKey(),
                'target' => 'answer',
                'option_id' => $option->getKey(),
                'category' => 'incorrect',
                'message' => 'Đáp án này cần kiểm tra lại.',
            ])
            ->assertCreated();

        $feedback = QuestionFeedback::query()->firstOrFail();
        $this->assertSame($this->user->id, $feedback->user_id);
        $this->assertSame('answer', $feedback->target);
        $this->assertSame($option->getKey(), $feedback->question_option_id);
        $this->assertSame('pending', $feedback->status);
    }

    /**
     * @param  list<string>  $examKeys
     * @param  list<string>  $articleKeys
     * @param  list<string>  $symptomKeys
     */
    private function assignScopes(
        Question $question,
        array $examKeys,
        array $articleKeys,
        array $symptomKeys,
    ): void {
        $groups = [
            [QuestionScopeType::Exam, $examKeys],
            [QuestionScopeType::Article, $articleKeys],
            [QuestionScopeType::Symptom, $symptomKeys],
        ];

        foreach ($groups as [$type, $keys]) {
            foreach ($keys as $key) {
                QuestionScope::create([
                    'question_id' => $question->getKey(),
                    'scope_type' => $type,
                    'scope_key' => $key,
                ]);
            }
        }
    }
}
