<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Auth\Models\LearnerProfile;
use Modules\Auth\Models\Profession;
use Modules\Personalization\Models\BookmarkFolder;
use Modules\Personalization\Models\BookmarkFolderItem;
use Modules\QuestionBank\Actions\RepeatQuestionSessionAction;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionScopeType;
use Modules\QuestionBank\Enums\QuestionStatus as PublicationStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionScope;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionSessionSnapshot;
use Modules\QuestionBank\Models\QuestionStatus;
use Modules\QuestionBank\Services\QuestionKeyInfoRenderer;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class QuestionBankFlowTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private User $user;

    private Lesson $topic;

    private Profession $profession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);
        $this->profession = Profession::query()->create([
            'code' => 'qbank-flow-student',
            'name' => 'Sinh viên Y',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        LearnerProfile::query()->create([
            'user_id' => $this->user->id,
            'profession_id' => $this->profession->id,
            'onboarding_completed_at' => now(),
        ]);
        $this->topic = $this->makeLesson([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-qbank-test',
            'sort_order' => 1,
        ]);
    }

    public function test_builder_uses_real_taxonomy_and_returns_a_live_count(): void
    {
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu miễn phí 1');
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu miễn phí 2');
        $this->createQuestion($this->topic, false, Difficulty::Easy, 'Câu premium');

        // The builder renders the real content taxonomy (organ systems / subjects)
        // server-side; lessons are fetched lazily. Provide a subject so the
        // learner-facing picker has data to display.
        $this->makeSubject(['name' => 'Tim mạch']);

        Blueprint::query()->create([
            'name' => 'Kỳ thi đánh giá năng lực hành nghề Bác sĩ Y khoa',
            'slug' => 'ky-thi-danh-gia-nang-luc',
            'code' => 'medical_practice_licensing_exam',
            'description' => 'Exam blueprint — 17 sections, 128 core clinical topics.',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $catalog = ExamCatalog::query()->create([
            'name' => 'Kỳ thi đánh giá năng lực hành nghề Bác sĩ Y khoa',
            'slug' => 'ky-thi-danh-gia-nang-luc',
            'code' => 'KY-QBANK-FLOW',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $catalog->professions()->sync([$this->profession->id]);

        $builderResponse = $this->actingAs($this->user)
            ->get(route('qbank.create'))
            ->assertOk()
            ->assertSee('Tim mạch')
            ->assertSeeInOrder([
                'Chọn loại phiên luyện',
                'Phiên luyện tập theo bài',
                'Phiên luyện tập thích ứng',
                'Thiết lập chủ đề',
                'Chế độ học tập',
                'Bắt đầu',
            ])
            ->assertSee('Tiêu chí phiên luyện')
            ->assertSee('Kỳ thi')
            ->assertSee('Kỳ thi đánh giá năng lực hành nghề Bác sĩ Y khoa')
            ->assertSee('Bài học')
            ->assertSee('Điểm yếu')
            ->assertSee('Cân bằng')
            ->assertSee('Củng cố')
            ->assertDontSee('Bài viết')
            ->assertDontSee('Triệu chứng')
            ->assertDontSee('Chủ đề lâm sàng')
            ->assertDontSee('>Tags</span>', false)
            ->assertDontSee('>Ma trận đề thi</span>', false)
            ->assertDontSee('Bác sĩ nội trú')
            ->assertDontSee('USMLE Step 2 CK')
            ->assertDontSee('ABCDE approach')
            ->assertDontSee('Acute coronary syndromes')
            ->assertDontSee('Tất cả (trong ngân hàng câu hỏi)')
            ->assertDontSee('Tất cả (trong kỳ thi đã chọn)')
            ->assertSee('activeTaxonomyScope()')
            ->assertSee('taxonomyLocked()')
            ->assertSee('Rất dễ')
            ->assertSee('Rất khó')
            ->assertSee('name="difficulties[]"', false)
            ->assertSee('1 phút 30 giây mỗi câu')
            ->assertSee(':disabled="matching === 0"', false)
            ->assertSee(':max="Math.max(0, questionLimit())"', false)
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

    public function test_adaptive_session_defaults_to_all_exams_and_ignores_difficulty_status_filters(): void
    {
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu adaptive 1');
        $this->createQuestion($this->topic, true, Difficulty::Hard, 'Câu adaptive 2');

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), [
                'mode' => SessionMode::Study->value,
                'source' => 'weak_topics',
                'adaptive_focus' => 'balanced',
                'count' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $blueprint = Blueprint::query()->create([
            'name' => 'Đề thích ứng',
            'slug' => 'de-thich-ung',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $section = BlueprintSection::query()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'Phần 1',
            'slug' => 'phan-1-adaptive',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $coreTopic = CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $section->id,
            'name' => 'CCT adaptive',
            'slug' => 'cct-adaptive',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $coreTopic->lessons()->sync([$this->topic->id]);
        Question::query()
            ->whereHas('lessons', fn ($lessons) => $lessons->where('lessons.id', $this->topic->id))
            ->each(fn (Question $question) => $question->blueprints()->sync([$blueprint->id]));

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), [
                'mode' => SessionMode::Study->value,
                'source' => 'weak_topics',
                'adaptive_focus' => 'weak_focus',
                'count' => 10,
                'blueprint_id' => $blueprint->id,
                'difficulties' => [Difficulty::Hard->value],
                'question_statuses' => ['incorrect'],
            ])
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->actingAs($this->user)
            ->post(route('qbank.store'), [
                'mode' => SessionMode::Study->value,
                'source' => 'weak_topics',
                'adaptive_focus' => 'retention',
                'count' => 2,
                'blueprint_id' => $blueprint->id,
                'difficulties' => [Difficulty::Hard->value],
                'question_statuses' => ['incorrect'],
            ])
            ->assertRedirect();

        $session = QuestionSession::query()->latest('id')->firstOrFail();
        $this->assertSame('weak_topics', $session->source->value);
        $this->assertSame($blueprint->id, $session->filters['blueprint_id']);
        $this->assertSame('retention', $session->filters['adaptive_focus']);
        $this->assertSame([], $session->filters['difficulties']);
        $this->assertSame([], $session->filters['question_statuses']);
        $this->assertSame(2, $session->total);
        $this->assertSame('Phiên luyện thích ứng', $session->displayName());
    }

    public function test_correct_status_filter_includes_latest_answers_that_used_a_hint(): void
    {
        $plain = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Đúng không gợi ý');
        $hinted = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Đúng có gợi ý');
        $wrong = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Làm sai');
        $earlier = QuestionSession::query()->create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'source' => 'custom',
            'question_ids' => [(string) $hinted->getKey()],
            'total' => 1,
        ]);
        $session = QuestionSession::query()->create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'source' => 'custom',
            'question_ids' => [
                (string) $plain->getKey(),
                (string) $hinted->getKey(),
                (string) $wrong->getKey(),
            ],
            'total' => 3,
        ]);

        QuestionAttempt::query()->create([
            'session_id' => $earlier->getKey(),
            'user_id' => $this->user->id,
            'question_id' => $hinted->getKey(),
            'is_correct' => false,
            'used_hint' => false,
            'answered_at' => now()->subMinute(),
        ]);
        foreach ([
            [$plain, true, false],
            [$hinted, true, true],
            [$wrong, false, false],
        ] as [$question, $isCorrect, $usedHint]) {
            QuestionAttempt::query()->create([
                'session_id' => $session->getKey(),
                'user_id' => $this->user->id,
                'question_id' => $question->getKey(),
                'is_correct' => $isCorrect,
                'used_hint' => $usedHint,
                'answered_at' => now(),
            ]);
        }

        $payload = [
            'mode' => SessionMode::Study->value,
            'source' => 'custom',
            'count' => 1,
            'lesson_ids' => [$this->topic->id],
            'question_status_mode' => 'latest',
        ];

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), [...$payload, 'question_statuses' => ['correct']])
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), [...$payload, 'question_statuses' => ['correct_with_hints']])
            ->assertOk()
            ->assertJsonPath('data.count', 1);

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), [...$payload, 'question_statuses' => ['incorrect']])
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_flagged_status_filter_includes_flagged_questions_only(): void
    {
        $flaggedAnswer = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu gắn cờ lúc nộp');
        $flaggedNote = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu gắn cờ trước khi nộp');
        $plain = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu không gắn cờ');
        $session = QuestionSession::query()->create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Active,
            'source' => 'custom',
            'question_ids' => [
                (string) $flaggedAnswer->getKey(),
                (string) $flaggedNote->getKey(),
                (string) $plain->getKey(),
            ],
            'annotations' => [
                (string) $flaggedNote->getKey() => ['flagged' => true],
                (string) $plain->getKey() => ['flagged' => false],
            ],
            'total' => 3,
        ]);
        QuestionAttempt::query()->create([
            'session_id' => $session->getKey(),
            'user_id' => $this->user->id,
            'question_id' => $flaggedAnswer->getKey(),
            'is_correct' => false,
            'flagged' => true,
            'answered_at' => now(),
        ]);
        QuestionAttempt::query()->create([
            'session_id' => $session->getKey(),
            'user_id' => $this->user->id,
            'question_id' => $plain->getKey(),
            'is_correct' => true,
            'flagged' => false,
            'answered_at' => now(),
        ]);

        $payload = [
            'mode' => SessionMode::Study->value,
            'source' => 'custom',
            'count' => 1,
            'lesson_ids' => [$this->topic->id],
            'question_status_mode' => 'latest',
        ];

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), [...$payload, 'question_statuses' => ['flagged']])
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->actingAs($this->user)
            ->get(route('qbank.create'))
            ->assertOk()
            ->assertSee('Đã gắn cờ')
            ->assertDontSee('Đã đánh dấu');
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

    public function test_custom_session_persists_display_name_from_builder(): void
    {
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu đặt tên');

        $payload = array_merge($this->sessionPayload(count: 1), [
            'name' => '  Ôn tim mạch sáng nay  ',
        ]);

        $this->actingAs($this->user)
            ->post(route('qbank.store'), $payload)
            ->assertRedirect();

        $session = QuestionSession::firstOrFail();
        $this->assertSame('Ôn tim mạch sáng nay', $session->filters['name']);
        $this->assertSame('Ôn tim mạch sáng nay', $session->displayName());
    }

    public function test_organ_system_and_subject_filters_are_anded_when_counting(): void
    {
        $cardio = $this->makeOrganSystem(['name' => 'Hệ tim mạch', 'slug' => 'he-tim-and-count']);
        $anatomy = $this->makeSubject(['name' => 'Giải phẫu', 'slug' => 'giai-phau-and-count']);
        $pathology = $this->makeSubject(['name' => 'Bệnh học', 'slug' => 'benh-hoc-and-count']);

        $both = $this->makeLesson([
            'name' => 'Cả hai',
            'slug' => 'ca-hai-and-count',
            'organSystems' => [$cardio],
            'subjects' => [$anatomy],
        ]);
        $cardioPath = $this->makeLesson([
            'name' => 'Chỉ tim bệnh học',
            'slug' => 'tim-benh-and-count',
            'organSystems' => [$cardio],
            'subjects' => [$pathology],
        ]);

        $this->createQuestion($both, true, Difficulty::Easy, 'Match AND');
        $this->createQuestion($cardioPath, true, Difficulty::Easy, 'Cardio only path');

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), [
                'mode' => SessionMode::Study->value,
                'source' => 'custom',
                'count' => 10,
                'organ_system_ids' => [$cardio->id],
                'subject_ids' => [$anatomy->id],
                'question_status_mode' => 'latest',
                'saved_only' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_empty_organ_subject_intersection_counts_zero_not_full_bank(): void
    {
        $cardio = $this->makeOrganSystem(['name' => 'Hệ tim mạch', 'slug' => 'he-tim-empty-intersect']);
        $resp = $this->makeOrganSystem(['name' => 'Hệ hô hấp', 'slug' => 'he-ho-hap-empty-intersect']);
        $anatomy = $this->makeSubject(['name' => 'Giải phẫu', 'slug' => 'giai-phau-empty-intersect']);
        $pathology = $this->makeSubject(['name' => 'Bệnh học', 'slug' => 'benh-hoc-empty-intersect']);

        $cardioPath = $this->makeLesson([
            'name' => 'Tim ∩ Bệnh học',
            'slug' => 'tim-benh-empty-intersect',
            'organSystems' => [$cardio],
            'subjects' => [$pathology],
        ]);
        $respAnatomy = $this->makeLesson([
            'name' => 'Hô hấp ∩ Giải phẫu',
            'slug' => 'ho-hap-giai-empty-intersect',
            'organSystems' => [$resp],
            'subjects' => [$anatomy],
        ]);

        $this->createQuestion($cardioPath, true, Difficulty::Easy, 'Cardio pathology');
        $this->createQuestion($respAnatomy, true, Difficulty::Easy, 'Resp anatomy');
        // Unrelated published question — must NOT inflate count when ∩ is empty.
        $this->createQuestion($this->topic, true, Difficulty::Easy, 'Outside filter');

        $payload = [
            'mode' => SessionMode::Study->value,
            'source' => 'custom',
            'count' => 10,
            'organ_system_ids' => [$cardio->id],
            'subject_ids' => [$anatomy->id],
            'question_status_mode' => 'latest',
            'saved_only' => false,
        ];

        $this->actingAs($this->user)
            ->postJson(route('qbank.count'), $payload)
            ->assertOk()
            ->assertJsonPath('data.count', 0);

        $this->actingAs($this->user)
            ->from(route('qbank.create'))
            ->post(route('qbank.store'), $payload)
            ->assertRedirect(route('qbank.create'))
            ->assertSessionHasErrors();
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

    public function test_key_info_underlines_only_hint_phrases_that_match_the_stem(): void
    {
        $stem = '[Amboss] Ca lâm sàng #064 – Skin & Subcutaneous Tissue. '
            .'Viêm khớp gối nóng đỏ, dịch đục, sốt. '
            .'Xét nghiệm dịch khớp ưu tiên để loại trừ?';
        $renderer = app(QuestionKeyInfoRenderer::class);

        $this->assertSame([], $renderer->resolvePhrases($stem, []));
        $this->assertStringNotContainsString('data-key-info', $renderer->render($stem, []));

        $phrases = $renderer->resolvePhrases($stem, ['dịch đục', 'không nằm trong đề']);
        $html = $renderer->render($stem, $phrases);

        $this->assertSame(['dịch đục', 'không nằm trong đề'], $phrases);
        $this->assertSame(1, substr_count($html, 'data-key-info'));
        $this->assertStringContainsString(
            '<span data-key-info class="underline decoration-amber-600 decoration-2 underline-offset-2">'
                .'dịch đục</span>',
            $html,
        );
        $this->assertStringNotContainsString('underline decoration-amber-600 decoration-2 underline-offset-2">Viêm khớp gối', $html);
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
        $outsideTopic = $this->makeLesson([
            'name' => 'Hô hấp',
            'slug' => 'ho-hap-qbank-test',
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
        $this->assertSame([$this->topic->id], $session->filters['lesson_ids']);
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
        $session->forceFill([
            'question_ids' => [(string) $first->getKey(), (string) $second->getKey()],
        ])->save();

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
            ->assertSee('keyInfoEnabled: true', false)
            ->assertSee('keyInfoUsed: true', false)
            ->assertSee('attendingTipOpen: false', false)
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
                    ->assertSee('keyInfoUsed: true', false)
                    ->assertSee('attendingTipOpen: false', false)
                    ->assertSee('attendingTipUsed: false', false)
                    ->assertSee('selected: '.$option->id, false)
                    ->assertSee("expandedOptions: JSON.parse('[".$option->id."]')", false);
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
            ->assertSee('Giải thích')
            ->assertSee('attendingTipOpen: true', false)
            ->assertSee('attendingTipUsed: true', false)
            ->assertSee('keyInfoEnabled: false', false)
            ->assertSee('keyInfoUsed: false', false);

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
            ->assertViewHas('correctWithHintCount', 1)
            ->assertSee('50% có gợi ý')
            ->assertSee('data-testid="hint-correct-rate"', false)
            ->assertViewHas('questionOverview', function (array $rows): bool {
                return count($rows) === 2
                    && collect($rows)->every(fn (array $row): bool => $row['peer_accuracy'] === 50
                        && $row['peer_users'] === 2
                        && $row['time_spent_seconds'] === 30
                        && $row['difficulty'] === 'Trung bình');
            })
            ->assertSee('Tóm tắt nhanh')
            ->assertDontSee('Tỷ lệ đúng theo chủ đề')
            ->assertDontSee('id="student-session-topic-accuracy"', false)
            ->assertSee('Phân tích bài theo phiên')
            ->assertSee('Tổng quan từng câu')
            ->assertSee('Thời gian cho mỗi câu hỏi')
            ->assertSee('Thống kê đồng nghiệp')
            ->assertSee('Khó khăn')
            ->assertSee('data-testid="question-overview-table"', false)
            ->assertSee('Quay lại ngân hàng câu hỏi');

        $this->actingAs($this->user)
            ->get(route('qbank.review', $session))
            ->assertOk()
            ->assertViewHas('items', function (array $items) use ($first, $second): bool {
                if (count($items) !== 2) {
                    return false;
                }

                $byId = collect($items)->keyBy('question_id');
                $firstItem = $byId[(string) $first->getKey()] ?? null;
                $secondItem = $byId[(string) $second->getKey()] ?? null;

                return is_array($firstItem)
                    && is_array($secondItem)
                    && $firstItem['hint_used'] === true
                    && $firstItem['has_key_info'] === true
                    && $firstItem['knowledge_used'] === false
                    && str_contains((string) $firstItem['stem_key_info_html'], 'data-key-info')
                    && str_contains((string) $firstItem['stem_key_info_html'], 'Study first')
                    && str_contains((string) $firstItem['knowledge_html'], 'Gợi ý dành cho câu hỏi đầu tiên.')
                    && $secondItem['hint_used'] === false
                    && $secondItem['has_key_info'] === true
                    && $secondItem['knowledge_used'] === true
                    && str_contains((string) $secondItem['stem_key_info_html'], 'Study second')
                    && str_contains((string) $secondItem['knowledge_html'], 'Gợi ý dành cho câu hỏi thứ hai.');
            })
            ->assertSee($first->stem)
            ->assertSee($second->stem)
            ->assertSee('data-testid="review-list-hint-used"', false)
            ->assertSee('data-testid="review-hint-toggle"', false)
            ->assertSee('data-testid="review-key-info-stem"', false)
            ->assertSee('data-testid="review-knowledge-panel"', false)
            ->assertSee('data-key-info', false)
            ->assertSee('Đã dùng gợi ý')
            ->assertSee('Đã dùng kiến thức');
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

    public function test_qbank_review_marks_hint_use_and_shows_hint_and_knowledge(): void
    {
        $withHint = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Bệnh nhân đau ngực khi gắng sức');
        $withKnowledge = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Bệnh nhân khó thở khi nằm');
        $legacyHint = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu gợi ý cũ');
        $withHint->update([
            'key_info' => ['đau ngực', ''],
            'attending_tip' => '<p>STEMI cần PCI cấp cứu.</p>',
        ]);
        $withKnowledge->update([
            'key_info' => ['khó thở'],
            'attending_tip' => 'Đánh giá suy hô hấp.',
        ]);

        $session = QuestionSession::create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'source' => 'custom',
            'question_ids' => [$withHint->id, $withKnowledge->id, $legacyHint->id],
            'total' => 3,
            'answered_count' => 3,
            'correct_count' => 2,
            'annotations' => [
                (string) $withHint->id => ['key_info_used' => true],
                (string) $withKnowledge->id => ['attending_tip_used' => true],
            ],
        ]);
        app(QuestionSessionSnapshots::class)->capture($session);

        foreach ([
            [$withHint, true],
            [$withKnowledge, true],
            [$legacyHint, true],
        ] as [$question, $usedHint]) {
            QuestionAttempt::factory()->create([
                'session_id' => $session->getKey(),
                'user_id' => $this->user->id,
                'question_id' => $question->id,
                'is_correct' => true,
                'used_hint' => $usedHint,
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('qbank.review', $session))
            ->assertOk()
            ->assertViewHas('items', function (array $items) use ($withHint, $withKnowledge, $legacyHint): bool {
                $byId = collect($items)->keyBy('question_id');
                $hintItem = $byId[(string) $withHint->getKey()];
                $knowledgeItem = $byId[(string) $withKnowledge->getKey()];
                $legacyItem = $byId[(string) $legacyHint->getKey()];

                return $hintItem['hint_used'] === true
                    && $hintItem['has_key_info'] === true
                    && $hintItem['knowledge_used'] === false
                    && str_contains((string) $hintItem['stem_key_info_html'], 'data-key-info')
                    && str_contains((string) $hintItem['stem_key_info_html'], 'đau ngực')
                    && ! str_contains((string) $hintItem['stem_html'], 'data-key-info')
                    && str_contains((string) $hintItem['knowledge_html'], 'STEMI cần PCI cấp cứu.')
                    && $knowledgeItem['hint_used'] === false
                    && $knowledgeItem['has_key_info'] === true
                    && $knowledgeItem['knowledge_used'] === true
                    && str_contains((string) $knowledgeItem['stem_key_info_html'], 'khó thở')
                    && str_contains((string) $knowledgeItem['knowledge_html'], 'Đánh giá suy hô hấp.')
                    && $legacyItem['hint_used'] === true
                    && $legacyItem['has_key_info'] === false
                    && ! str_contains((string) $legacyItem['stem_key_info_html'], 'data-key-info');
            })
            ->assertSee('data-testid="review-hint-toggle"', false)
            ->assertSee('data-testid="review-key-info-stem"', false)
            ->assertSee('data-key-info', false)
            ->assertDontSee('review-hint-panel', false)
            ->assertSee('Gợi ý')
            ->assertSee('Kiến thức')
            ->assertSee('STEMI cần PCI cấp cứu', false);
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

        app(QuestionSessionSnapshots::class)->capture($session);

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

        $this->actingAs($this->user)->post(route('qbank.store'), $payload)->assertRedirect(route('qbank.session', QuestionSession::first()));
        $session = QuestionSession::firstOrFail();
        $this->assertSame(180, $session->time_limit_seconds);
        $firstSessionQuestion = Question::findOrFail($session->question_ids[0]);

        $this->actingAs($this->user)
            ->get(route('qbank.session', $session))
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
            ->get(route('qbank.session', [$session, 'index' => 1]))
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
                    ->assertRedirect(route('qbank.session', [$session, 'index' => 1]));
            }
        }

        $this->assertSame(SessionStatus::Active, $session->refresh()->status);
        $this->assertSame(2, $session->answered_count);
        $this->assertSame(0, $session->correct_count);
        $this->assertSame(2, QuestionStatus::where('user_id', $this->user->id)->count());
        $this->assertSame(2, QuestionStatus::where('status', UserQuestionStatus::Unseen)->count());
        $this->assertSame(2, QuestionAttempt::whereNull('is_correct')->count());

        $this->actingAs($this->user)
            ->post(route('qbank.session.finish', $session))
            ->assertRedirect(route('qbank.summary', $session));

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

    public function test_pausing_a_completed_session_redirects_to_summary_instead_of_throwing_409(): void
    {
        $session = QuestionSession::factory()->create([
            'user_id' => $this->user->id,
            'status' => SessionStatus::Completed,
        ]);

        $this->actingAs($this->user)
            ->post(route('qbank.session.pause', $session), ['current_index' => 0])
            ->assertRedirect(route('qbank.summary', $session))
            ->assertSessionHas('status', 'Phiên đã hoàn thành nên không thể tạm dừng.');

        $this->assertSame(SessionStatus::Completed, $session->refresh()->status);
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
                ->get(route('qbank.session', $session))
                ->assertOk()
                ->assertViewHas('remainingSeconds', 480);

            $this->actingAs($this->user)
                ->post(route('qbank.session.resume', $session))
                ->assertRedirect(route('qbank.session', [$session, 'index' => 0]));

            Carbon::setTestNow('2026-08-06 09:03:00');
            $this->actingAs($this->user)
                ->get(route('qbank.session', $session))
                ->assertOk()
                ->assertViewHas('remainingSeconds', 420);
            $this->actingAs($this->user)
                ->post(route('qbank.session.pause', $session), ['current_index' => 0])
                ->assertRedirect(route('qbank.index'));
            $this->assertSame(420, $session->refresh()->paused_state['timer_remaining_seconds']);

            Carbon::setTestNow('2026-08-06 10:03:00');
            $this->actingAs($this->user)
                ->get(route('qbank.session', $session))
                ->assertOk()
                ->assertViewHas('remainingSeconds', 420);

            $this->actingAs($this->user)->post(route('qbank.session.resume', $session));
            Carbon::setTestNow('2026-08-06 10:10:01');
            $this->actingAs($this->user)
                ->get(route('qbank.session', $session))
                ->assertRedirect(route('qbank.summary', $session));
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

    public function test_history_answered_question_stat_counts_each_question_once(): void
    {
        $repeated = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu làm lại');
        $once = $this->createQuestion($this->topic, true, Difficulty::Easy, 'Câu làm một lần');
        $first = QuestionSession::create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'source' => 'custom',
            'question_ids' => [$repeated->getKey()],
            'total' => 1,
            'answered_count' => 1,
            'correct_count' => 1,
        ]);
        $second = QuestionSession::create([
            'user_id' => $this->user->id,
            'mode' => SessionMode::Study,
            'status' => SessionStatus::Completed,
            'source' => 'custom',
            'question_ids' => [$repeated->getKey(), $once->getKey()],
            'total' => 2,
            'answered_count' => 2,
            'correct_count' => 1,
        ]);

        foreach ([$first, $second] as $session) {
            QuestionAttempt::create([
                'session_id' => $session->getKey(),
                'user_id' => $this->user->id,
                'question_id' => $repeated->getKey(),
                'selected_option_ids' => [],
                'is_correct' => true,
                'answered_at' => now(),
            ]);
        }
        QuestionAttempt::create([
            'session_id' => $second->getKey(),
            'user_id' => $this->user->id,
            'question_id' => $once->getKey(),
            'selected_option_ids' => [],
            'is_correct' => false,
            'answered_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('qbank.index'))
            ->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                return $stats['answered_questions'] === 2
                    && $stats['accuracy'] === 66.7;
            });
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

    /** @return array<string, mixed> */
    private function sessionPayload(
        int $count,
        Difficulty $difficulty = Difficulty::Easy,
    ): array {
        return [
            'mode' => SessionMode::Study->value,
            'source' => 'custom',
            'count' => $count,
            'lesson_ids' => [$this->topic->id],
            'difficulty' => $difficulty->value,
            'question_status_mode' => 'latest',
            'saved_only' => false,
        ];
    }

    private function createQuestion(
        Lesson $topic,
        bool $isFree,
        Difficulty $difficulty,
        string $stem,
        ?string $stemImagePath = null,
    ): Question {
        $question = Question::create([
            'stem' => $stem,
            'stem_image_path' => $stemImagePath,
            'difficulty' => $difficulty,
            'status' => PublicationStatus::Published,
            'is_free' => $isFree,
        ]);

        QuestionOption::create([
            'question_id' => $question->getKey(),
            'label' => 'A',
            'content' => 'Đáp án đúng '.$stem,
            'is_correct' => true,
            'explanation' => 'Giải thích cho '.$stem,
            'order' => 0,
        ]);
        QuestionOption::create([
            'question_id' => $question->getKey(),
            'label' => 'B',
            'content' => 'Đáp án sai '.$stem,
            'is_correct' => false,
            'order' => 1,
        ]);

        $question->lessons()->sync([$topic->id]);
        $question->professions()->sync([$this->profession->id]);

        return $question;
    }

    public function test_summary_shows_cumulative_lesson_progress(): void
    {
        foreach (['Một', 'Hai', 'Ba', 'Bốn'] as $stem) {
            $this->createQuestion($this->topic, true, Difficulty::Easy, $stem);
        }

        $this->actingAs($this->user)->post(route('qbank.store'), $this->sessionPayload(count: 2));
        $session = QuestionSession::firstOrFail();

        foreach ($session->question_ids as $index => $questionId) {
            $question = Question::with('options')->findOrFail($questionId);
            $wrong = $question->options->firstWhere('is_correct', false);

            $this->actingAs($this->user)->post(route('qbank.session.answer', $session), [
                'question_id' => $questionId,
                'option_ids' => [$wrong->id],
                'index' => $index,
            ]);
        }

        $this->actingAs($this->user)->post(route('qbank.session.finish', $session));

        $this->actingAs($this->user)
            ->get(route('qbank.summary', $session))
            ->assertOk()
            ->assertSee('Đề xuất học tập')
            ->assertSee('data-testid="lesson-analysis-tabs"', false)
            ->assertSee('data-testid="lesson-progress"', false)
            ->assertSee('role="tablist"', false)
            ->assertSee('Tim mạch')
            ->assertSee('0% đúng')
            ->assertSee('width: 50%', false)
            ->assertSee('Tổng số câu')
            ->assertSee('Câu đúng')
            ->assertSee('Đúng có gợi ý')
            ->assertSee('Câu sai')
            ->assertSee('Chưa làm')
            ->assertSee('data-testid="lesson-'.$this->topic->id.'-total">4', false)
            ->assertSee('data-testid="lesson-'.$this->topic->id.'-correct">0', false)
            ->assertSee('data-testid="lesson-'.$this->topic->id.'-hint">0', false)
            ->assertSee('data-testid="lesson-'.$this->topic->id.'-wrong">2', false)
            ->assertSee('data-testid="lesson-'.$this->topic->id.'-not-done">2', false)
            ->assertSee('Cần ôn');
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
