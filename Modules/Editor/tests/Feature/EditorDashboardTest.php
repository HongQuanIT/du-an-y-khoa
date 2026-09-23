<?php

declare(strict_types=1);

namespace Modules\Editor\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Tests\TestCase;

final class EditorDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_content_editor_uses_a_dedicated_portal_and_only_sees_own_questions(): void
    {
        $editor = $this->editor('Biên tập viên chính');
        $other = $this->editor('Biên tập viên khác');
        Question::factory()->for($editor, 'creator')->create(['code' => 'EDITOR-OWN', 'status' => QuestionStatus::Draft]);
        Question::factory()->for($other, 'creator')->create(['code' => 'EDITOR-OTHER']);

        $this->actingAsEditor($editor)
            ->get(route('editor.dashboard'))
            ->assertOk()
            ->assertSee('Bảng điều khiển biên tập')
            ->assertSee('EDITOR-OWN')
            ->assertDontSee('EDITOR-OTHER')
            ->assertSee('Nhịp độ biên tập')
            ->assertSee('dashboard-charts.js');

        $this->actingAsEditor($editor)
            ->get(route('editor.questions.create'))
            ->assertOk()
            ->assertSee('Tạo câu hỏi mới');

        $this->actingAsEditor($editor)
            ->getJson(route('editor.questions.eligible-instructors'))
            ->assertOk()
            ->assertJsonStructure(['instructors']);

        $this->actingAsEditor($editor)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAsEditor($editor)->get(route('admin.questions.create'))
            ->assertRedirect(route('editor.questions.create'));
    }

    public function test_non_editor_cannot_access_editor_portal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $this->actingAs($admin)->get(route('editor.dashboard'))->assertForbidden();
    }

    private function editor(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole(Role::ContentEditor->value);
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [],
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function actingAsEditor(User $user): static
    {
        return $this->actingAs($user)->withSession([TwoFactorSession::KEY => now()->timestamp]);
    }
}
