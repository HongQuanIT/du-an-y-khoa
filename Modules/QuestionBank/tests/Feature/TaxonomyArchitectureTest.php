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
use Modules\QuestionBank\Models\MedicalTaxonomy;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
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

    public function test_medical_taxonomy_supports_nested_nodes(): void
    {
        $taxonomy = MedicalTaxonomy::query()->create([
            'name' => 'MedLearn Medical Taxonomy',
            'code' => 'medlearn-test',
            'status' => TaxonomyStatus::Active,
        ]);

        $cardiology = MedicalTaxonomyNode::query()->create([
            'medical_taxonomy_id' => $taxonomy->id,
            'name' => 'Cardiology',
            'slug' => 'cardiology',
            'node_type' => 'specialty',
            'status' => TaxonomyStatus::Active,
        ]);

        $acs = MedicalTaxonomyNode::query()->create([
            'medical_taxonomy_id' => $taxonomy->id,
            'parent_id' => $cardiology->id,
            'name' => 'Acute coronary syndrome',
            'slug' => 'acs',
            'node_type' => 'condition',
            'status' => TaxonomyStatus::Active,
        ]);

        $this->assertSame($cardiology->id, $acs->parent->id);
        $this->assertCount(1, $cardiology->fresh()->children);
    }

    public function test_core_topic_can_map_to_multiple_medical_nodes(): void
    {
        [$coreTopic, $nodeA, $nodeB] = $this->seedCoreTopicAndNodes();

        $coreTopic->medicalTaxonomyNodes()->sync([$nodeA->id, $nodeB->id]);

        $this->assertCount(2, $coreTopic->fresh()->medicalTaxonomyNodes);
        $this->assertCount(1, $nodeA->fresh()->coreClinicalTopics);
    }

    public function test_blueprint_filter_matches_via_mapping_without_direct_pivot(): void
    {
        [$coreTopic, $nodeA] = $this->seedCoreTopicAndNodes();
        $coreTopic->medicalTaxonomyNodes()->sync([$nodeA->id]);

        $match = Question::factory()->create(['status' => QuestionStatus::Published]);
        $match->medicalTaxonomyNodes()->sync([$nodeA->id]);
        $other = Question::factory()->create(['status' => QuestionStatus::Published]);

        $repo = app(QuestionRepository::class);
        $byCore = $repo->paginatePublished(new ListQuestionsData(
            coreClinicalTopicIds: [$coreTopic->id],
        ));

        $this->assertTrue($byCore->contains('id', $match->id));
        $this->assertFalse($byCore->contains('id', $other->id));
        $this->assertSame(0, $match->coreClinicalTopics()->count());
        $this->assertTrue($match->inferredCoreClinicalTopics()->contains('id', $coreTopic->id));
    }

    public function test_blueprint_filter_matches_via_tag_mapping(): void
    {
        [$coreTopic] = $this->seedCoreTopicAndNodes();
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
        $taxonomy = $this->makeMedicalTaxonomy('scope-test-taxonomy');
        $system = $this->makeMedicalNode([
            'taxonomy' => $taxonomy,
            'name' => 'Hệ tim mạch',
            'slug' => 'he-tim-mach-scope',
            'node_type' => 'system',
        ]);
        $specialty = $this->makeMedicalNode([
            'taxonomy' => $taxonomy,
            'parent_id' => $system->id,
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-scope',
            'node_type' => 'specialty',
        ]);
        $disease = $this->makeMedicalNode([
            'taxonomy' => $taxonomy,
            'parent_id' => $specialty->id,
            'name' => 'STEMI scope',
            'slug' => 'stemi-scope',
            'node_type' => 'disease',
        ]);
        $otherSystem = $this->makeMedicalNode([
            'taxonomy' => $taxonomy,
            'name' => 'Hệ bài tiết',
            'slug' => 'he-bai-tiet-scope',
            'node_type' => 'system',
        ]);

        [$coreTopic] = $this->seedCoreTopicAndNodes();
        $coreTopic->medicalTaxonomyNodes()->sync([$disease->id]);

        $scopes = app(\Modules\QuestionBank\Support\QuestionFilterBuilder::class)
            ->taxonomyScopesForBlueprints([$coreTopic->section->blueprint_id]);

        $scope = $scopes[$coreTopic->section->blueprint_id];
        $this->assertContains($system->id, $scope['systemIds']);
        $this->assertContains($specialty->id, $scope['specialtyIds']);
        $this->assertContains($disease->id, $scope['nodeIds']);
        $this->assertNotContains($otherSystem->id, $scope['systemIds']);
        $this->assertNotContains($otherSystem->id, $scope['nodeIds']);
    }

    public function test_question_pivot_relationships_are_unique(): void
    {
        [, $nodeA] = $this->seedCoreTopicAndNodes();
        $tag = Tag::query()->create([
            'name' => 'ECG',
            'slug' => 'ecg',
            'status' => TaxonomyStatus::Active,
        ]);

        $question = Question::factory()->create([
            'status' => QuestionStatus::Published,
        ]);
        $question->medicalTaxonomyNodes()->sync([$nodeA->id]);
        $question->tags()->sync([$tag->id]);

        $question->medicalTaxonomyNodes()->syncWithoutDetaching([$nodeA->id]);
        $question->tags()->syncWithoutDetaching([$tag->id]);

        $this->assertSame(1, $question->medicalTaxonomyNodes()->count());
        $this->assertSame(1, $question->tags()->count());
    }

    public function test_repository_filters_by_blueprint_via_medical_mapping(): void
    {
        [$coreTopic, $nodeA] = $this->seedCoreTopicAndNodes();
        $coreTopic->medicalTaxonomyNodes()->sync([$nodeA->id]);
        $tag = Tag::query()->create(['name' => 'Emergency', 'slug' => 'emergency', 'status' => TaxonomyStatus::Active]);

        $match = Question::factory()->create([
            'difficulty' => Difficulty::Hard,
            'status' => QuestionStatus::Published,
        ]);
        $match->medicalTaxonomyNodes()->sync([$nodeA->id]);
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
        $byMedical = $repo->paginatePublished(new ListQuestionsData(
            medicalTaxonomyNodeIds: [$nodeA->id],
        ));
        $byTag = $repo->paginatePublished(new ListQuestionsData(
            tagIds: [$tag->id],
        ));
        $combined = $repo->paginatePublished(new ListQuestionsData(
            coreClinicalTopicIds: [$coreTopic->id],
            medicalTaxonomyNodeIds: [$nodeA->id],
            tagIds: [$tag->id],
            difficulty: Difficulty::Hard->value,
        ));

        $this->assertTrue($byBlueprint->contains('id', $match->id));
        $this->assertTrue($byCoreTopic->contains('id', $match->id));
        $this->assertTrue($byMedical->contains('id', $match->id));
        $this->assertTrue($byTag->contains('id', $match->id));
        $this->assertSame(1, $combined->total());
    }

    public function test_save_question_requires_medical_taxonomy_nodes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\Modules\Admin\Actions\SaveAdminQuestionAction::class)->handle($admin, null, [
            'stem' => '<p>Test stem</p>',
            'explanation' => '<p>Explanation</p>',
            'difficulty' => Difficulty::Medium->value,
            'medical_taxonomy_node_ids' => [],
            'is_free' => false,
            'options' => [
                ['content' => 'A', 'is_correct' => true, 'explanation' => 'ok'],
                ['content' => 'B', 'is_correct' => false],
            ],
        ]);
    }

    public function test_save_question_syncs_medical_taxonomy_nodes(): void
    {
        $node = $this->makeMedicalNode(['name' => 'STEMI', 'node_type' => 'disease']);

        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $question = app(\Modules\Admin\Actions\SaveAdminQuestionAction::class)->handle($admin, null, [
            'stem' => '<p>Test stem</p>',
            'explanation' => '<p>Explanation</p>',
            'difficulty' => Difficulty::Medium->value,
            'medical_taxonomy_node_ids' => [$node->id],
            'is_free' => false,
            'options' => [
                ['content' => 'A', 'is_correct' => true, 'explanation' => 'ok'],
                ['content' => 'B', 'is_correct' => false],
            ],
        ]);

        $this->assertTrue($question->medicalTaxonomyNodes()->whereKey($node->id)->exists());
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

    /** @return array{0: CoreClinicalTopic, 1: MedicalTaxonomyNode, 2: MedicalTaxonomyNode} */
    private function seedCoreTopicAndNodes(): array
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

        $taxonomy = MedicalTaxonomy::query()->create([
            'name' => 'Tax',
            'code' => 'tax-'.uniqid(),
            'status' => TaxonomyStatus::Active,
        ]);
        $nodeA = MedicalTaxonomyNode::query()->create([
            'medical_taxonomy_id' => $taxonomy->id,
            'name' => 'Node A',
            'slug' => 'node-a-'.uniqid(),
            'status' => TaxonomyStatus::Active,
        ]);
        $nodeB = MedicalTaxonomyNode::query()->create([
            'medical_taxonomy_id' => $taxonomy->id,
            'name' => 'Node B',
            'slug' => 'node-b-'.uniqid(),
            'status' => TaxonomyStatus::Active,
        ]);

        return [$coreTopic, $nodeA, $nodeB];
    }
}
