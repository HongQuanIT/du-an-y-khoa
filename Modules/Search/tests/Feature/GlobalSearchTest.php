<?php

declare(strict_types=1);

namespace Modules\Search\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Library\Database\Seeders\LibraryDatabaseSeeder;
use Modules\Library\Models\Article;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Topic;
use Modules\Search\Database\Seeders\SearchDatabaseSeeder;
use Modules\Search\Models\SearchDocument;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

final class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::findOrCreate(Role::Student->value, 'web');
        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);

        Topic::query()->create([
            'name' => 'Hô hấp',
            'slug' => 'ho-hap-global-search-test',
            'type' => 'system',
            'order' => 1,
        ]);
    }

    public function test_global_search_indexes_library_and_question_bank_content(): void
    {
        $topic = Topic::query()->firstOrFail();

        Question::query()->create([
            'stem' => 'Viêm phổi cộng đồng cần điều trị thế nào?',
            'explanation' => 'Dùng kháng sinh theo mức độ nặng.',
            'topic_id' => $topic->getKey(),
            'difficulty' => 'easy',
            'status' => QuestionStatus::Published,
            'is_free' => true,
        ]);

        Article::query()->create([
            'type' => 'article',
            'slug' => 'viem-phoi-cong-dong-test',
            'title' => 'Viêm phổi cộng đồng',
            'summary' => 'Bài đọc nền tảng về viêm phổi.',
            'body' => '<p>Viêm phổi cộng đồng là nhiễm trùng nhu mô phổi.</p>',
            'is_free' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->artisan('db:seed', [
            '--class' => SearchDatabaseSeeder::class,
            '--force' => true,
        ])->assertExitCode(0);

        $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'viêm phổi']))
            ->assertOk()
            ->assertSee('Kết quả cho “viêm phổi”')
            ->assertSee('Viêm phổi cộng đồng', false);

        $this->actingAs($this->user)
            ->getJson(route('search.suggest', ['q' => 'viêm', 'limit' => 5]))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'text', 'type', 'scope', 'highlight', 'url']]]);

        $this->assertGreaterThanOrEqual(2, SearchDocument::query()->count());
    }

    public function test_empty_global_search_shows_recent_or_popular_suggestions(): void
    {
        $this->actingAs($this->user)
            ->get(route('search.index'))
            ->assertOk()
            ->assertSee('Gợi ý gần đây');
    }
}
