<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Data\ListQuestionsData;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Tag;
use Modules\QuestionBank\Repositories\QuestionRepository;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class TaxonomyArchitectureTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_blueprint_section_and_core_topic_can_be_created(): void
    {
        $blueprint = Blueprint::query()->create([
            'name' => 'Test Blueprint',
            'slug' => 'test-blueprint',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);

        $section = BlueprintSection::query()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'Hệ tim mạch',
            'slug' => 'he-tim-mach',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);

        $topic = CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $section->id,
            'name' => 'Đau ngực',
            'slug' => 'dau-nguc',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);

        $this->assertSame($blueprint->id, $section->blueprint->id);
        $this->assertSame($section->id, $topic->section->id);
    }

    public function test_curriculum_taxonomy_links_organ_system_subject_and_lesson(): void
    {
        $organSystem = $this->makeOrganSystem(['name' => 'Hệ tim mạch']);
        $subject = $this->makeSubject([
            'name' => 'Nội tim mạch',
            'organSystems' => [$organSystem],
        ]);
        $lesson = $this->makeLesson([
            'name' => 'Nhồi máu cơ tim',
            'subjects' => [$subject],
        ]);

        $this->assertTrue($subject->organSystems()->whereKey($organSystem->id)->exists());
        $this->assertTrue($lesson->subjects()->whereKey($subject->id)->exists());
        $this->assertTrue($organSystem->subjects()->whereKey($subject->id)->exists());
        $this->assertTrue($subject->lessons()->whereKey($lesson->id)->exists());
    }

    public function test_question_infers_subjects_and_organ_systems_from_lessons_only(): void
    {
        $organSystem = $this->makeOrganSystem(['name' => 'Hệ hô hấp']);
        $subject = $this->makeSubject([
            'name' => 'Hô hấp học',
            'organSystems' => [$organSystem],
        ]);
        $lesson = $this->makeLesson([
            'name' => 'Viêm phổi',
            'subjects' => [$subject],
        ]);

        $question = Question::factory()->create(['status' => QuestionStatus::Published]);
        $question->lessons()->attach($lesson->id);

        $this->assertTrue($question->inferredSubjects()->contains('id', $subject->id));
        $this->assertTrue($question->inferredOrganSystems()->contains('id', $organSystem->id));
    }

    public function test_core_topic_can_map_to_multiple_lessons(): void
    {
        [$coreTopic, $lessonA, $lessonB] = $this->seedCoreTopicAndLessons();

        $coreTopic->lessons()->sync([$lessonA->id, $lessonB->id]);

        $this->assertCount(2, $coreTopic->fresh()->lessons);
        $this->assertCount(1, $lessonA->fresh()->coreClinicalTopics);
    }

    public function test_blueprint_filter_matches_via_mapping_without_direct_pivot(): void
    {
        [$coreTopic, $lessonA] = $this->seedCoreTopicAndLessons();
        $coreTopic->lessons()->sync([$lessonA->id]);

        $match = Question::factory()->create(['status' => QuestionStatus::Published]);
        $match->lessons()->attach($lessonA->id);
        $other = Question::factory()->create(['status' => QuestionStatus::Published]);

        $repo = app(QuestionRepository::class);
        $byCore = $repo->paginatePublished(new ListQuestionsData(
            coreClinicalTopicIds: [$coreTopic->id],
        ));

        $this->assertTrue($byCore->contains('id', $match->id));
        $this->assertFalse($byCore->contains('id', $other->id));
        $this->assertTrue($match->inferredCoreClinicalTopics()->contains('id', $coreTopic->id));
    }

    public function test_blueprint_filter_matches_via_tag_mapping(): void
    {
        [$coreTopic] = $this->seedCoreTopicAndLessons();
        $tag = Tag::query()->create([
            'name' => 'STEMI Tag Map',
            'slug' => 'stemi-tag-map',
            'status' => TaxonomyStatus::Active,
        ]);
        $coreTopic->tags()->sync([$tag->id]);

        $match = Question::factory()->create(['status' => QuestionStatus::Published]);
        $match->tags()->sync([$tag->id]);
        $other = Question::factory()->create(['status' => QuestionStatus::Published]);

        $repo = app(QuestionRepository::class);
        $byCore = $repo->paginatePublished(new ListQuestionsData(
            coreClinicalTopicIds: [$coreTopic->id],
        ));

        $this->assertTrue($byCore->contains('id', $match->id));
        $this->assertFalse($byCore->contains('id', $other->id));
        $this->assertTrue($match->inferredCoreClinicalTopics()->contains('id', $coreTopic->id));
    }

    public function test_blueprint_taxonomy_scope_includes_ancestors_for_ui_filters(): void
    {
        $organSystem = $this->makeOrganSystem([
            'name' => 'Hệ tim mạch',
            'slug' => 'he-tim-mach-scope',
        ]);
        $subject = $this->makeSubject([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-scope',
            'organSystems' => [$organSystem],
        ]);
        $lesson = $this->makeLesson([
            'name' => 'STEMI scope',
            'slug' => 'stemi-scope',
            'subjects' => [$subject],
        ]);

        $otherOrganSystem = $this->makeOrganSystem([
            'name' => 'Hệ bài tiết',
            'slug' => 'he-bai-tiet-scope',
        ]);
        $otherSubject = $this->makeSubject([
            'name' => 'Thận tiết niệu',
            'slug' => 'than-tiet-nieu-scope',
            'organSystems' => [$otherOrganSystem],
        ]);
        $otherLesson = $this->makeLesson([
            'name' => 'Suy thận scope',
            'slug' => 'suy-than-scope',
            'subjects' => [$otherSubject],
        ]);

        [$coreTopic] = $this->seedCoreTopicAndLessons();
        $coreTopic->lessons()->sync([$lesson->id]);

        $scopes = app(\Modules\QuestionBank\Support\QuestionFilterBuilder::class)
            ->taxonomyScopesForBlueprints([$coreTopic->section->blueprint_id]);

        $scope = $scopes[$coreTopic->section->blueprint_id];
        $this->assertContains($organSystem->id, $scope['organSystemIds']);
        $this->assertContains($subject->id, $scope['subjectIds']);
        $this->assertContains($lesson->id, $scope['lessonIds']);
        $this->assertNotContains($otherOrganSystem->id, $scope['organSystemIds']);
        $this->assertNotContains($otherSubject->id, $scope['subjectIds']);
        $this->assertNotContains($otherLesson->id, $scope['lessonIds']);
    }

    public function test_question_pivot_relationships_are_unique(): void
    {
        [, $lessonA] = $this->seedCoreTopicAndLessons();
        $tag = Tag::query()->create([
            'name' => 'ECG',
            'slug' => 'ecg',
            'status' => TaxonomyStatus::Active,
        ]);

        $question = Question::factory()->create([
            'status' => QuestionStatus::Published,
        ]);
        $question->lessons()->sync([$lessonA->id]);
        $question->tags()->sync([$tag->id]);

        $question->lessons()->syncWithoutDetaching([$lessonA->id]);
        $question->tags()->syncWithoutDetaching([$tag->id]);

        $this->assertSame(1, $question->lessons()->count());
        $this->assertSame(1, $question->tags()->count());
    }

    public function test_repository_filters_by_blueprint_via_lesson_mapping(): void
    {
        [$coreTopic, $lessonA] = $this->seedCoreTopicAndLessons();
        $coreTopic->lessons()->sync([$lessonA->id]);
        $tag = Tag::query()->create(['name' => 'Emergency', 'slug' => 'emergency', 'status' => TaxonomyStatus::Active]);

        $match = Question::factory()->create([
            'difficulty' => Difficulty::Hard,
            'status' => QuestionStatus::Published,
        ]);
        $match->lessons()->attach($lessonA->id);
        $match->tags()->sync([$tag->id]);

        Question::factory()->create([
            'difficulty' => Difficulty::Easy,
            'status' => QuestionStatus::Published,
        ]);

        $repo = app(QuestionRepository::class);

        $byBlueprint = $repo->paginatePublished(new ListQuestionsData(
            blueprintId: $coreTopic->section->blueprint_id,
        ));
        $byCoreTopic = $repo->paginatePublished(new ListQuestionsData(
            coreClinicalTopicIds: [$coreTopic->id],
        ));
        $byLesson = $repo->paginatePublished(new ListQuestionsData(
            lessonIds: [$lessonA->id],
        ));
        $byTag = $repo->paginatePublished(new ListQuestionsData(
            tagIds: [$tag->id],
        ));
        $combined = $repo->paginatePublished(new ListQuestionsData(
            coreClinicalTopicIds: [$coreTopic->id],
            lessonIds: [$lessonA->id],
            tagIds: [$tag->id],
            difficulty: Difficulty::Hard->value,
        ));

        $this->assertTrue($byBlueprint->contains('id', $match->id));
        $this->assertTrue($byCoreTopic->contains('id', $match->id));
        $this->assertTrue($byLesson->contains('id', $match->id));
        $this->assertTrue($byTag->contains('id', $match->id));
        $this->assertSame(1, $combined->total());
    }

    public function test_save_question_requires_lessons(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\Modules\Admin\Actions\SaveAdminQuestionAction::class)->handle($admin, null, [
            'stem' => '<p>Test stem</p>',
            'explanation' => '<p>Explanation</p>',
            'difficulty' => Difficulty::Medium->value,
            'lesson_ids' => [],
            'is_free' => false,
            'options' => [
                ['content' => 'A', 'is_correct' => true, 'explanation' => 'ok'],
                ['content' => 'B', 'is_correct' => false],
            ],
        ]);
    }

    public function test_save_question_syncs_lessons(): void
    {
        $lesson = $this->makeLesson(['name' => 'STEMI']);

        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $question = app(\Modules\Admin\Actions\SaveAdminQuestionAction::class)->handle($admin, null, [
            'stem' => '<p>Test stem</p>',
            'explanation' => '<p>Explanation</p>',
            'difficulty' => Difficulty::Medium->value,
            'lesson_ids' => [$lesson->id],
            'is_free' => false,
            'options' => [
                ['content' => 'A', 'is_correct' => true, 'explanation' => 'ok'],
                ['content' => 'B', 'is_correct' => false],
            ],
        ]);

        $this->assertTrue($question->lessons()->whereKey($lesson->id)->exists());
    }

    public function test_medical_licensing_exam_blueprint_seeder_is_idempotent(): void
    {
        $this->seed(\Modules\QuestionBank\Database\Seeders\MedicalLicensingExamBlueprintSeeder::class);
        $this->seed(\Modules\QuestionBank\Database\Seeders\MedicalLicensingExamBlueprintSeeder::class);

        $this->assertSame(1, Blueprint::query()->where('code', 'medical_practice_licensing_exam')->count());
        $this->assertSame(17, BlueprintSection::query()->count());
        $this->assertSame(128, CoreClinicalTopic::query()->count());
        $this->assertSame(
            'Chủng ngừa (Tiêm ngừa/Tiêm phòng) trẻ em và người lớn',
            CoreClinicalTopic::query()->where('slug', 'chung-ngua-tiem-nguatiem-phong-tre-em-va-nguoi-lon')->value('name'),
        );
    }

    /** @return array{0: CoreClinicalTopic, 1: Lesson, 2: Lesson} */
    private function seedCoreTopicAndLessons(): array
    {
        $blueprint = Blueprint::query()->create([
            'name' => 'Blueprint',
            'slug' => 'bp',
            'status' => TaxonomyStatus::Active,
        ]);
        $section = BlueprintSection::query()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'Section',
            'slug' => 'section',
            'status' => TaxonomyStatus::Active,
        ]);
        $coreTopic = CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $section->id,
            'name' => 'Core',
            'slug' => 'core',
            'status' => TaxonomyStatus::Active,
        ]);

        $lessonA = $this->makeLesson([
            'name' => 'Lesson A',
            'slug' => 'lesson-a-'.uniqid(),
        ]);
        $lessonB = $this->makeLesson([
            'name' => 'Lesson B',
            'slug' => 'lesson-b-'.uniqid(),
        ]);

        return [$coreTopic, $lessonA, $lessonB];
    }
}
