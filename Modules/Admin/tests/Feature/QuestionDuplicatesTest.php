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
use Modules\QuestionBank\Enums\DuplicateSeverity;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class QuestionDuplicatesTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    private MedicalTaxonomyNode $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->topic = $this->makeMedicalNode([
            'name' => 'Tim mạch dup',
            'slug' => 'tim-mach-dup-test',
            'node_type' => 'specialty',
            'sort_order' => 1,
        ]);
    }

    public function test_editor_opens_duplicate_detail_page_and_sees_matches(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $a = $this->makeQuestion('Bệnh nhân 55 tuổi đau ngực ACS?', [
            ['content' => 'ACS', 'is_correct' => true],
            ['content' => 'GERD', 'is_correct' => false],
        ], $editor);
        $b = $this->makeQuestion('<p>Bệnh nhân 55 tuổi đau ngực ACS?</p>', [
            ['content' => 'GERD', 'is_correct' => false],
            ['content' => 'ACS', 'is_correct' => true],
        ], $editor);

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.edit', $a))
            ->assertOk()
            ->assertSee('Kiểm tra trùng lặp')
            ->assertSee(route('admin.questions.duplicates.show', $a), false);

        $this->actingAsStaff($editor)
            ->get(route('admin.questions.duplicates.show', $a))
            ->assertOk()
            ->assertSee('Kiểm tra trùng lặp')
            ->assertSee('Kết quả trong ngân hàng')
            ->assertSee('100%')
            ->assertSee('Trùng khớp 100%');

        $this->assertDatabaseHas('question_similarity_matches', [
            'severity' => DuplicateSeverity::Exact->value,
            'score' => 100,
        ]);

        $this->actingAsStaff($editor)
            ->post(route('admin.questions.check-duplicates', $a))
            ->assertRedirect(route('admin.questions.duplicates.show', $a));

        $this->assertNotNull($b->fresh());
    }

    public function test_publisher_can_open_duplicate_check_on_read_only_edit_page(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $admin = $this->staffUser(Role::Admin);
        $superAdmin = $this->staffUser(Role::SuperAdmin);

        $question = $this->makeQuestion('Stem kiểm tra trùng cho publisher?', [
            ['content' => 'A', 'is_correct' => true],
            ['content' => 'B', 'is_correct' => false],
        ], $editor);

        foreach ([$admin, $superAdmin] as $publisher) {
            $html = $this->actingAsStaff($publisher)
                ->get(route('admin.questions.edit', $question))
                ->assertOk()
                ->assertSee('Kiểm tra trùng lặp')
                ->assertSee(route('admin.questions.duplicates.show', $question), false)
                ->getContent();

            $dupPos = strpos($html, route('admin.questions.duplicates.show', $question));
            $this->assertNotFalse($dupPos);
            $before = substr($html, max(0, $dupPos - 1200), 1200);
            $this->assertStringNotContainsString(
                'pointer-events-none',
                $before,
                'Link kiểm tra trùng lặp không được nằm trong vùng pointer-events-none',
            );

            $this->actingAsStaff($publisher)
                ->get(route('admin.questions.duplicates.show', $question))
                ->assertOk()
                ->assertSee('Kiểm tra trùng lặp');

            $this->actingAsStaff($publisher)
                ->post(route('admin.questions.check-duplicates', $question))
                ->assertRedirect(route('admin.questions.duplicates.show', $question));
        }
    }

    public function test_student_cannot_open_duplicate_page(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $question = $this->makeQuestion('Stem only for access test?', [
            ['content' => 'A', 'is_correct' => true],
            ['content' => 'B', 'is_correct' => false],
        ], $editor);

        $this->actingAs($student)
            ->get(route('admin.questions.duplicates.show', $question))
            ->assertForbidden();
    }

    /**
     * @param  list<array{content: string, is_correct: bool}>  $options
     */
    private function makeQuestion(string $stem, array $options, ?User $createdBy = null): Question
    {
        $question = Question::factory()->create([
            'stem' => $stem,
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::Draft,
            'is_free' => true,
            'version' => 0,
            'created_by' => $createdBy?->id,
        ]);
        $question->medicalTaxonomyNodes()->sync([$this->topic->id]);

        foreach ($options as $i => $row) {
            $question->options()->create([
                'label' => chr(65 + $i),
                'content' => $row['content'],
                'is_correct' => $row['is_correct'],
                'order' => $i + 1,
            ]);
        }

        return $question->fresh('options');
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
