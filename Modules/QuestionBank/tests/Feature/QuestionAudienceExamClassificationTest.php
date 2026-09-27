<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\LearnerProfile;
use Modules\Auth\Models\Profession;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Services\SessionQuestionSelector;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class QuestionAudienceExamClassificationTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    public function test_exam_pool_uses_blueprint_and_profession_membership(): void
    {
        RoleModel::findOrCreate(Role::Student->value, 'web');
        $this->seed(RolePermissionSeeder::class);

        $resident = Profession::query()->create([
            'code' => 'resident',
            'name' => 'Bác sĩ nội trú',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $student = Profession::query()->create([
            'code' => 'medical_student',
            'name' => 'Sinh viên y',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $lesson = $this->makeLesson(['name' => 'Nhồi máu cơ tim']);
        $blueprint = Blueprint::query()->create([
            'name' => 'Nội trú 2026',
            'slug' => 'noi-tru-2026',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
        ]);
        $blueprint->professions()->sync([$resident->id]);
        $otherExam = Blueprint::query()->create([
            'name' => 'Tốt nghiệp Y6',
            'slug' => 'tot-nghiep-y6',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 2,
        ]);
        $otherExam->professions()->sync([$student->id]);

        $forResident = $this->publishedQuestion('STEMI', $lesson->id);
        $forResident->professions()->sync([$resident->id]);
        $forResident->blueprints()->sync([$blueprint->id]);

        $sameLessonOtherAudience = $this->publishedQuestion('Troponin', $lesson->id);
        $sameLessonOtherAudience->professions()->sync([$student->id]);
        $sameLessonOtherAudience->blueprints()->sync([$blueprint->id]);

        $residentUser = User::factory()->create();
        $residentUser->assignRole(Role::Student->value);
        LearnerProfile::query()->create([
            'user_id' => $residentUser->id,
            'profession_id' => $resident->id,
            'onboarding_completed_at' => now(),
        ]);

        $selector = app(SessionQuestionSelector::class);
        $data = new CreateSessionData(
            mode: SessionMode::Study,
            source: SessionSource::WeakTopics,
            count: 10,
            blueprintId: $blueprint->id,
        );

        $ids = $selector->forSession($residentUser, $data);

        $this->assertContains($forResident->id, $ids);
        $this->assertNotContains($sameLessonOtherAudience->id, $ids);

        $this->actingAs($residentUser)
            ->get(route('qbank.create'))
            ->assertOk()
            ->assertSee('Nội trú 2026')
            ->assertDontSee('Tốt nghiệp Y6');
    }

    private function publishedQuestion(string $stem, int $lessonId): Question
    {
        $question = Question::factory()->create([
            'stem' => $stem,
            'status' => QuestionStatus::Published,
            'is_free' => true,
        ]);
        $question->lessons()->sync([$lessonId]);

        return $question;
    }
}
