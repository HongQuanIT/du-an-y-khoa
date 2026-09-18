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
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Tests\TestCase;

final class AdminExamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_index_lists_learner_created_exams_read_only(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $learner = User::factory()->create(['name' => 'Nguyen Van A', 'email' => 'learner@example.com']);
        $blueprint = Blueprint::query()->create([
            'name' => 'TNLS 2026',
            'slug' => 'tnls-2026',
            'code' => 'TNLS',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'total_questions' => 100,
        ]);

        Exam::query()->create([
            'user_id' => $learner->id,
            'blueprint_id' => $blueprint->id,
            'title' => 'TNLS 2026',
            'description' => 'Bài thi học viên',
            'duration_minutes' => 150,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.exams.index'))
            ->assertOk()
            ->assertSee('Bài thi')
            ->assertSee('Nguyen Van A')
            ->assertSee('TNLS 2026')
            ->assertSee('learner@example.com')
            ->assertDontSee('Tạo kỳ thi');
    }

    public function test_admin_can_view_exam_detail(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $learner = User::factory()->create(['name' => 'Tran B']);
        $exam = Exam::query()->create([
            'user_id' => $learner->id,
            'title' => 'Bài thi chi tiết',
            'duration_minutes' => 90,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);

        $this->actingAsStaff($admin)
            ->get(route('admin.exams.show', $exam))
            ->assertOk()
            ->assertSee('Bài thi chi tiết')
            ->assertSee('Tran B')
            ->assertSee('Chỉ xem');
    }

    public function test_admin_create_routes_are_removed(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->actingAsStaff($admin)
            ->get('/admin/exams/create')
            ->assertNotFound();
    }

    public function test_admin_can_delete_exam(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $exam = Exam::query()->create([
            'title' => 'Xóa tôi',
            'duration_minutes' => 60,
            'status' => ExamStatus::Draft,
            'is_published' => false,
        ]);

        $this->actingAsStaff($admin)
            ->delete(route('admin.exams.destroy', $exam))
            ->assertRedirect(route('admin.exams.index'));

        $this->assertSame(0, Exam::query()->count());
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->enrollTwoFactor($user);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }

    private function enrollTwoFactor(User $user): void
    {
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);
    }
}
