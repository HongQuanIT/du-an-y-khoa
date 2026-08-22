<?php

declare(strict_types=1);

namespace Modules\Search\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Topic;
use Modules\Search\Actions\SearchScopeAction;
use Modules\Search\Data\SearchQueryData;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

final class ContextualSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::findOrCreate(Role::Student->value, 'web');
        $this->user = User::factory()->create();
        $this->user->assignRole(Role::Student->value);
        $this->topic = Topic::query()->create([
            'name' => 'Hô hấp',
            'slug' => 'ho-hap-contextual-search-test',
            'type' => 'system',
            'order' => 1,
        ]);
    }

    public function test_scoped_search_falls_back_with_facets_highlight_and_current_db_gating(): void
    {
        $free = $this->question(
            '<strong>Viêm phổi</strong> cộng đồng cần điều trị thế nào?',
            free: true,
            difficulty: Difficulty::Easy,
        );
        $premium = $this->question('Viêm phổi Premium bí mật', free: false);
        $draft = $this->question('Viêm phổi bản nháp bí mật', free: true, status: QuestionStatus::Draft);
        $this->question('Câu hỏi tim mạch không liên quan', free: true);

        $result = app(SearchScopeAction::class)->handle(new SearchQueryData(
            scope: 'qbank',
            query: 'viêm phổi',
            filters: ['is_free' => true],
        ), $this->user);
        $items = $result->items();

        $this->assertCount(1, $items);
        $this->assertSame((string) $free->getKey(), $items[0]['id']);
        $this->assertTrue($items[0]['attributes']['is_free']);
        $this->assertSame('database', $result->engine);
        $this->assertTrue($result->degraded);
        $this->assertSame(1, $result->pagination()['total']);
        $this->assertNotContains((string) $premium->getKey(), collect($items)->pluck('id')->all());
        $this->assertNotContains((string) $draft->getKey(), collect($items)->pluck('id')->all());

        $highlight = (string) $items[0]['highlight'];
        $this->assertStringContainsString('<mark>Viêm phổi</mark>', $highlight);
        $this->assertStringNotContainsString('<strong>', $highlight);
        $this->assertSame('easy', $result->facets['difficulty'][0]['value']);
        $this->assertSame(1, $result->facets['difficulty'][0]['count']);
    }

    public function test_database_fallback_expands_and_highlights_medical_synonyms(): void
    {
        $question = $this->question('Community-acquired pneumonia cần điều trị thế nào?', free: true);

        $result = app(SearchScopeAction::class)->handle(new SearchQueryData(
            scope: 'qbank',
            query: 'viêm phổi',
            filters: ['is_free' => true],
        ), $this->user);
        $items = $result->items();

        $this->assertCount(1, $items);
        $this->assertSame((string) $question->getKey(), $items[0]['id']);
        $this->assertStringContainsString('<mark>pneumonia</mark>', (string) $items[0]['highlight']);
    }

    public function test_database_search_matches_a_secondary_pivot_topic_without_duplicate_results(): void
    {
        $secondaryTopic = Topic::query()->create([
            'name' => 'Truyền nhiễm',
            'slug' => 'truyen-nhiem-contextual-search-test',
            'type' => 'system',
            'order' => 2,
        ]);
        $question = $this->question('Viêm phổi nhiễm khuẩn cần chọn kháng sinh', free: true);
        $question->topics()->syncWithoutDetaching([$secondaryTopic->getKey()]);

        $result = app(SearchScopeAction::class)->handle(new SearchQueryData(
            scope: 'qbank',
            query: 'viêm phổi',
            filters: ['topic_id' => $secondaryTopic->getKey(), 'is_free' => true],
        ), $this->user);

        $this->assertCount(1, $result->items());
        $this->assertSame((string) $question->getKey(), $result->items()[0]['id']);
        $this->assertEqualsCanonicalizing(
            [$this->topic->getKey(), $secondaryTopic->getKey()],
            $result->items()[0]['attributes']['topic_ids'],
        );
    }

    public function test_qbank_query_renders_contextual_results_and_filters(): void
    {
        $this->question('Ca lâm sàng viêm phổi cộng đồng', free: true, difficulty: Difficulty::Hard);
        $this->question('Ca lâm sàng viêm phổi Premium', free: false, difficulty: Difficulty::Hard);

        $this->actingAs($this->user)
            ->get(route('qbank.index', ['q' => 'viêm phổi']))
            ->assertOk()
            ->assertSee('Tìm trong ngân hàng câu hỏi')
            ->assertSee('<mark>viêm phổi</mark>', false)
            ->assertSee('Công cụ tìm kiếm đang tạm gián đoạn')
            ->assertSee('filter[difficulty]', false)
            ->assertSee('Ca lâm sàng', false)
            ->assertSee('cộng đồng', false)
            ->assertDontSee('Ca lâm sàng viêm phổi Premium');
    }

    public function test_existing_question_search_uses_the_same_safe_database_fallback(): void
    {
        $free = $this->question('Tổn thương phổi 100% đặc hiệu', free: true);
        $this->question('Tổn thương phổi 1000 đặc hiệu', free: true);
        $this->question('Tổn thương phổi 100% Premium', free: false);

        $this->actingAs($this->user)
            ->getJson(route('api.question-bank.questions.index', ['q' => '100%']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $free->getKey());
    }

    public function test_qbank_search_requires_at_least_two_characters(): void
    {
        $this->actingAs($this->user)
            ->get(route('qbank.index', ['q' => 'v']))
            ->assertOk()
            ->assertSee('Nhập ít nhất 2 ký tự để tìm kiếm.');
    }

    public function test_meilisearch_settings_define_filters_synonyms_and_after_commit(): void
    {
        $settings = config('scout.meilisearch.index-settings.'.Question::class);

        $this->assertTrue((bool) config('scout.after_commit'));
        $this->assertSame(['difficulty', 'topic_id', 'topic_ids', 'is_free'], $settings['filterableAttributes']);
        $this->assertContains('pneumonia', $settings['synonyms']['viêm phổi']);
        $this->assertSame(['stem'], $settings['searchableAttributes']);
    }

    private function question(
        string $stem,
        bool $free,
        Difficulty $difficulty = Difficulty::Medium,
        QuestionStatus $status = QuestionStatus::Published,
    ): Question {
        return Question::factory()->create([
            'stem' => $stem,
            'explanation' => 'Giải thích bí mật',
            'topic_id' => $this->topic->getKey(),
            'difficulty' => $difficulty,
            'status' => $status,
            'is_free' => $free,
        ]);
    }
}
