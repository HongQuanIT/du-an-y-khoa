<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\Profession;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Question;
use Tests\TestCase;

final class TaxonomyOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_overview_flags_questions_and_exams_learners_cannot_reach(): void
    {
        $question = Question::factory()->create();
        $lesson = Lesson::query()->create([
            'name' => 'Viêm gan',
            'slug' => 'viem-gan',
            'status' => TaxonomyStatus::Active,
        ]);
        $question->lessons()->sync([$lesson->id]);

        $blueprint = Blueprint::query()->create([
            'name' => 'Ma trận hành nghề',
            'slug' => 'ma-tran-hanh-nghe',
            'status' => TaxonomyStatus::Active,
        ]);
        $section = BlueprintSection::query()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'Nội',
            'slug' => 'noi',
        ]);
        CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $section->id,
            'name' => 'Suy tim',
            'slug' => 'suy-tim',
        ]);
        ExamCatalog::query()->create([
            'name' => 'Kỳ thi nội trú',
            'slug' => 'ky-thi-noi-tru',
            'status' => TaxonomyStatus::Active,
            'blueprint_id' => $blueprint->id,
        ]);

        $this->actingAsStaff($this->staffUser(Role::Admin))
            ->get(route('admin.taxonomy.index'))
            ->assertOk()
            ->assertSee('Câu xuất bản học viên thấy')
            ->assertSee('1 câu chưa gắn chức danh trên 1 câu xuất bản')
            ->assertSee('1 kỳ thi đang dùng chưa gắn đối tượng')
            ->assertSee('Chủ đề của kỳ thi đã gắn bài')
            ->assertSee('0 / 1')
            ->assertSee('Kỳ thi học viên chọn được')
            ->assertDontSee('Tình trạng phân loại')
            ->assertDontSee('128 chủ đề theo');
    }

    public function test_overview_stays_quiet_when_published_questions_are_reachable(): void
    {
        $profession = Profession::query()->create([
            'code' => 'NT',
            'name' => 'Bác sĩ nội trú',
            'is_active' => true,
        ]);
        $question = Question::factory()->create();
        $question->professions()->sync([$profession->id]);
        $organ = OrganSystem::query()->create([
            'name' => 'Tiêu hóa',
            'slug' => 'tieu-hoa',
            'status' => TaxonomyStatus::Active,
        ]);
        $lesson = Lesson::query()->create([
            'name' => 'Viêm gan',
            'slug' => 'viem-gan-ok',
            'status' => TaxonomyStatus::Active,
        ]);
        $lesson->organSystems()->sync([$organ->id]);
        $question->lessons()->sync([$lesson->id]);
        $catalog = ExamCatalog::query()->create([
            'name' => 'Kỳ thi nội trú',
            'slug' => 'ky-thi-ok',
            'status' => TaxonomyStatus::Active,
        ]);
        $catalog->professions()->sync([$profession->id]);
        $catalog->questions()->sync([$question->id]);

        $this->actingAsStaff($this->staffUser(Role::Admin))
            ->get(route('admin.taxonomy.index'))
            ->assertOk()
            ->assertSee('Mọi câu xuất bản đã gắn chức danh')
            ->assertDontSee('Tình trạng phân loại')
            ->assertDontSee('chưa gắn chức danh')
            ->assertDontSee('chưa gắn đối tượng');
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
