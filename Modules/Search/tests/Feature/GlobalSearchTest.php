<?php

declare(strict_types=1);

namespace Modules\Search\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Models\Classroom;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
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

    public function test_global_search_returns_library_content_without_qbank_questions(): void
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

        Classroom::query()->create([
            'title' => 'Lớp ôn thi hô hấp',
            'description' => 'Ôn tập tình huống lâm sàng hô hấp.',
            'host_user_id' => $this->user->getKey(),
            'visibility' => ClassroomVisibility::Public,
            'status' => ClassroomStatus::Active,
        ]);

        Exam::query()->create([
            'title' => 'Kỳ thi hô hấp tổng hợp',
            'description' => 'Đề mô phỏng chủ đề hô hấp.',
            'duration_minutes' => 90,
            'status' => ExamStatus::Published,
            'is_published' => true,
        ]);

        $this->artisan('db:seed', [
            '--class' => SearchDatabaseSeeder::class,
            '--force' => true,
        ])->assertExitCode(0);

        $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'viêm phổi']))
            ->assertOk()
            ->assertSee('Kết quả cho “viêm phổi”')
            ->assertSee('Viêm phổi cộng đồng', false)
            ->assertDontSee('cần điều trị thế nào');

        $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'hô hấp']))
            ->assertOk()
            ->assertSee('Lớp ôn thi hô hấp', false)
            ->assertSee('Kỳ thi hô hấp tổng hợp', false)
            ->assertDontSee('cần điều trị thế nào');

        $this->actingAs($this->user)
            ->getJson(route('search.suggest', ['q' => 'viêm', 'limit' => 5]))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'text', 'type', 'scope', 'highlight', 'url']]])
            ->assertJsonMissing(['scope' => 'qbank']);

        $this->assertGreaterThanOrEqual(2, SearchDocument::query()->count());
    }

    public function test_empty_global_search_shows_recent_or_popular_suggestions(): void
    {
        $this->actingAs($this->user)
            ->get(route('search.index'))
            ->assertOk()
            ->assertSee('Gợi ý gần đây');
    }

    public function test_search_redirects_to_exam_and_classroom_page_when_matching_name(): void
    {
        $exam = \Modules\Exam\Models\Exam::query()->create([
            'title' => 'Thi Thử Bác Sĩ Nội Trú 2026',
            'description' => 'Mô tả bài thi nội trú',
            'duration_minutes' => 60,
            'status' => \Modules\Exam\Enums\ExamStatus::Published,
            'is_published' => true,
        ]);

        $classroom = \Modules\Classroom\Models\Classroom::query()->create([
            'title' => 'Lớp Học Lâm Sàng Nội Khoa',
            'host_user_id' => $this->user->id,
            'purpose' => \Modules\Classroom\Enums\ClassroomPurpose::CommunityReview,
            'visibility' => \Modules\Classroom\Enums\ClassroomVisibility::Public,
            'status' => \Modules\Classroom\Enums\ClassroomStatus::Active,
        ]);

        $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'Thi Thử Bác Sĩ Nội Trú 2026']))
            ->assertRedirect(route('exam.index'));

        $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'Lớp Học Lâm Sàng Nội Khoa']))
            ->assertRedirect(route('classroom.show', $classroom));
    }
}
