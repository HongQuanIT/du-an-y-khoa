<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Data\ListQuestionsData;
use Modules\QuestionBank\Database\Seeders\MedicalKnowledgeTaxonomySeeder;
use Modules\QuestionBank\Database\Seeders\MedicalLicensingExamBlueprintSeeder;
use Modules\QuestionBank\Database\Seeders\QuestionDemoSeeder;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\MedicalTaxonomy;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Tag;
use Modules\QuestionBank\Repositories\QuestionRepository;
use Tests\TestCase;
use Tests\Support\CreatesMedicalTaxonomy;


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
        $this->assertSame(1, MedicalTaxonomy::query()->where('code', 'medlearn-medical-taxonomy')->count());
        $this->assertSame(1, MedicalTaxonomy::query()->count());
        $this->assertSame(1, Question::query()->where('code', 'CARDIO-STEMI-001')->count());
    }

    public function test_medical_taxonomy_nested_stemi_tree(): void
    {
        $taxonomy = MedicalTaxonomy::query()->where('code', 'medlearn-medical-taxonomy')->firstOrFail();
        $stemi = MedicalTaxonomyNode::query()
            ->where('medical_taxonomy_id', $taxonomy->id)
            ->where('slug', 'stemi')
            ->firstOrFail();

        $this->assertSame('disease', $stemi->node_type);
        $this->assertSame('Nhồi máu cơ tim', $stemi->parent?->name);
        $this->assertSame('Hội chứng vành cấp', $stemi->parent?->parent?->name);
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

    public function test_demo_question_links_blueprint_and_taxonomy(): void
    {
        $question = Question::query()
            ->where('code', 'CARDIO-STEMI-001')
            ->with(['coreClinicalTopics.section', 'medicalTaxonomyNodes', 'tags', 'hints'])
            ->firstOrFail();

        $this->assertTrue($question->coreClinicalTopics->contains(fn ($t) => $t->name === 'Đau ngực'));
        $this->assertTrue($question->coreClinicalTopics->contains(fn ($t) => $t->section?->name === 'Hệ tim mạch'));
        $this->assertTrue($question->medicalTaxonomyNodes->contains(fn ($n) => $n->slug === 'stemi'));
        $this->assertTrue($question->medicalTaxonomyNodes->contains(fn ($n) => $n->node_type === 'symptom' && $n->name === 'Đau ngực'));
        $this->assertTrue($question->medicalTaxonomyNodes->contains(fn ($n) => $n->node_type === 'clinical_finding'));
        $this->assertTrue($question->medicalTaxonomyNodes->contains(fn ($n) => $n->node_type === 'concept'));
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
        $stemi = MedicalTaxonomyNode::query()->where('slug', 'stemi')->firstOrFail();
        $concept = MedicalTaxonomyNode::query()->where('slug', 'concept-nhan-dien-stemi')->firstOrFail();
        $symptom = MedicalTaxonomyNode::query()->where('slug', 'symptom-dau-nguc')->firstOrFail();
        $tag = Tag::query()->where('slug', 'ecg')->firstOrFail();
        $demoId = Question::query()->where('code', 'CARDIO-STEMI-001')->value('id');

        $bySectionTopic = $repo->paginatePublished(new ListQuestionsData(
            blueprintSectionId: $section->id,
            coreClinicalTopicIds: [$coreTopic->id],
        ));
        $byDisease = $repo->paginatePublished(new ListQuestionsData(
            medicalTaxonomyNodeIds: [$stemi->id],
        ));
        $byConcept = $repo->paginatePublished(new ListQuestionsData(
            medicalTaxonomyNodeIds: [$concept->id],
        ));
        $bySymptom = $repo->paginatePublished(new ListQuestionsData(
            medicalTaxonomyNodeIds: [$symptom->id],
        ));
        $byTag = $repo->paginatePublished(new ListQuestionsData(
            tagIds: [$tag->id],
        ));
        $combined = $repo->paginatePublished(new ListQuestionsData(
            blueprintSectionId: $section->id,
            coreClinicalTopicIds: [$coreTopic->id],
            medicalTaxonomyNodeIds: [$stemi->id],
            tagIds: [$tag->id],
            difficulty: Difficulty::Hard->value,
        ));

        $this->assertTrue($bySectionTopic->contains('id', $demoId));
        $this->assertTrue($byDisease->contains('id', $demoId));
        $this->assertTrue($byConcept->contains('id', $demoId));
        $this->assertTrue($bySymptom->contains('id', $demoId));
        $this->assertTrue($byTag->contains('id', $demoId));
        $this->assertTrue($combined->contains('id', $demoId));

        $this->assertSame(
            1,
            Question::query()->where('code', 'CARDIO-STEMI-001')->firstOrFail()
                ->medicalTaxonomyNodes()->where('medical_taxonomy_nodes.id', $stemi->id)->count(),
        );
    }

    public function test_no_duplicate_pivot_rows(): void
    {
        $this->seed(QuestionDemoSeeder::class);

        $question = Question::query()->where('code', 'CARDIO-STEMI-001')->firstOrFail();

        $this->assertSame(
            $question->coreClinicalTopics()->count(),
            $question->coreClinicalTopics()->distinct('core_clinical_topics.id')->count('core_clinical_topics.id'),
        );
        $this->assertSame(
            $question->medicalTaxonomyNodes()->count(),
            $question->medicalTaxonomyNodes()->distinct('medical_taxonomy_nodes.id')->count('medical_taxonomy_nodes.id'),
        );
        $this->assertSame(
            $question->tags()->count(),
            $question->tags()->distinct('tags.id')->count('tags.id'),
        );
    }
}
