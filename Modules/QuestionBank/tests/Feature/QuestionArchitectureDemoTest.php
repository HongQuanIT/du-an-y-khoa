<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Data\ListQuestionsData;
use Modules\QuestionBank\Database\Seeders\MedicalKnowledgeTaxonomySeeder;
use Modules\QuestionBank\Database\Seeders\MedicalLicensingExamBlueprintSeeder;
use Modules\QuestionBank\Database\Seeders\QuestionDemoSeeder;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Subject;
use Modules\QuestionBank\Models\Tag;
use Modules\QuestionBank\Repositories\QuestionRepository;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;


final class QuestionArchitectureDemoTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MedicalLicensingExamBlueprintSeeder::class);
        $this->seed(MedicalKnowledgeTaxonomySeeder::class);
        $this->seed(QuestionDemoSeeder::class);
    }

    public function test_blueprint_architecture_counts(): void
    {
        $this->assertSame(1, Blueprint::query()->where('code', 'medical_practice_licensing_exam')->count());
        $this->assertSame(17, BlueprintSection::query()->count());
        $this->assertSame(128, CoreClinicalTopic::query()->count());
        $this->assertSame(128, CoreClinicalTopic::query()->distinct('name')->count('name'));
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed(MedicalLicensingExamBlueprintSeeder::class);
        $this->seed(MedicalKnowledgeTaxonomySeeder::class);
        $this->seed(QuestionDemoSeeder::class);

        $this->assertSame(1, Blueprint::query()->where('code', 'medical_practice_licensing_exam')->count());
        $this->assertSame(17, BlueprintSection::query()->count());
        $this->assertSame(128, CoreClinicalTopic::query()->count());
        $this->assertSame(
            count(MedicalKnowledgeTaxonomySeeder::DEMO_LESSON_SLUGS),
            Lesson::query()->whereIn('slug', MedicalKnowledgeTaxonomySeeder::DEMO_LESSON_SLUGS)->count(),
        );
        $this->assertSame(1, DB::table('organ_systems')->where('slug', 'he-tim-mach')->count());
        $this->assertSame(1, DB::table('subjects')->where('slug', 'tim-mach')->count());
        $this->assertSame(1, Question::query()->where('code', 'CARDIO-STEMI-001')->count());
    }

    public function test_demo_curriculum_links_stemi_lesson(): void
    {
        $stemi = Lesson::query()->where('slug', 'stemi')->firstOrFail();

        $subject = $stemi->subjects()->firstOrFail();
        $this->assertSame('Tim mạch', $subject->name);

        $organSystem = $subject->organSystems()->firstOrFail();
        $this->assertSame('Hệ tim mạch', $organSystem->name);

        $this->assertTrue(
            Subject::query()->where('slug', 'tim-mach')->firstOrFail()
                ->lessons()->where('lessons.slug', 'stemi')->exists(),
        );
    }

    public function test_demo_question_options_and_correct_answer(): void
    {
        $question = Question::query()
            ->where('code', 'CARDIO-STEMI-001')
            ->with(['options' => fn ($q) => $q->orderBy('order')])
            ->firstOrFail();

        $this->assertCount(5, $question->options);
        $correct = $question->options->firstWhere('is_correct', true);
        $this->assertNotNull($correct);
        $this->assertSame('B', $correct->label);
        $this->assertStringContainsString('STEMI', $correct->content);
    }

    public function test_demo_question_links_taxonomy_and_infers_blueprint(): void
    {
        $question = Question::query()
            ->where('code', 'CARDIO-STEMI-001')
            ->with(['lessons', 'tags', 'hints'])
            ->firstOrFail();

        $inferred = $question->inferredCoreClinicalTopics();
        $this->assertTrue($inferred->contains(fn ($t) => $t->name === 'Đau ngực'));
        $this->assertTrue($inferred->contains(fn ($t) => $t->section?->name === 'Hệ tim mạch'));
        $this->assertTrue($question->lessons->contains(fn ($l) => $l->slug === 'stemi'));
        $this->assertTrue($question->tags->contains(fn (Tag $t) => $t->type === 'symptom' && $t->name === 'Đau ngực'));
        $this->assertTrue($question->tags->contains(fn (Tag $t) => $t->type === 'clinical_finding'));
        $this->assertTrue($question->tags->contains(fn (Tag $t) => $t->type === 'concept'));
        $this->assertGreaterThanOrEqual(6, $question->tags->count());
        $this->assertTrue($question->tags->contains(fn (Tag $t) => $t->slug === 'ecg'));

        $this->assertCount(2, $question->hints);
        $this->assertSame(1, $question->hints[0]->sort_order);
        $this->assertSame(2, $question->hints[1]->sort_order);
    }

    public function test_query_use_cases_without_n_plus_one(): void
    {
        $repo = app(QuestionRepository::class);
        $section = BlueprintSection::query()->where('slug', 'he-tim-mach')->firstOrFail();
        $coreTopic = CoreClinicalTopic::query()->where('slug', 'dau-nguc')->firstOrFail();
        $stemiLesson = Lesson::query()->where('slug', 'stemi')->firstOrFail();
        $conceptTag = Tag::query()->where('slug', 'kn-nhan-dien-stemi')->firstOrFail();
        $symptomTag = Tag::query()->where('slug', 'trieu-chung-dau-nguc')->firstOrFail();
        $tag = Tag::query()->where('slug', 'ecg')->firstOrFail();
        $demoId = Question::query()->where('code', 'CARDIO-STEMI-001')->value('id');

        $bySectionTopic = $repo->paginatePublished(new ListQuestionsData(
            blueprintSectionId: $section->id,
            coreClinicalTopicIds: [$coreTopic->id],
        ));
        $byLesson = $repo->paginatePublished(new ListQuestionsData(
            lessonIds: [$stemiLesson->id],
        ));
        $byConceptTag = $repo->paginatePublished(new ListQuestionsData(
            tagIds: [$conceptTag->id],
        ));
        $bySymptomTag = $repo->paginatePublished(new ListQuestionsData(
            tagIds: [$symptomTag->id],
        ));
        $byTag = $repo->paginatePublished(new ListQuestionsData(
            tagIds: [$tag->id],
        ));
        $combined = $repo->paginatePublished(new ListQuestionsData(
            blueprintSectionId: $section->id,
            coreClinicalTopicIds: [$coreTopic->id],
            lessonIds: [$stemiLesson->id],
            tagIds: [$tag->id],
            difficulty: Difficulty::Hard->value,
        ));

        $this->assertTrue($bySectionTopic->contains('id', $demoId));
        $this->assertTrue($byLesson->contains('id', $demoId));
        $this->assertTrue($byConceptTag->contains('id', $demoId));
        $this->assertTrue($bySymptomTag->contains('id', $demoId));
        $this->assertTrue($byTag->contains('id', $demoId));
        $this->assertTrue($combined->contains('id', $demoId));

        $this->assertSame(
            1,
            Question::query()->where('code', 'CARDIO-STEMI-001')->firstOrFail()
                ->lessons()->where('lessons.id', $stemiLesson->id)->count(),
        );
    }

    public function test_no_duplicate_pivot_rows(): void
    {
        $this->seed(QuestionDemoSeeder::class);

        $question = Question::query()->where('code', 'CARDIO-STEMI-001')->firstOrFail();

        $this->assertSame(
            $question->lessons()->count(),
            $question->lessons()->distinct('lessons.id')->count('lessons.id'),
        );
        $this->assertSame(
            $question->tags()->count(),
            $question->tags()->distinct('tags.id')->count('tags.id'),
        );
    }
}
