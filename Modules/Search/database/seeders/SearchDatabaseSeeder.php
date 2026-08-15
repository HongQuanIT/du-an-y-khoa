<?php

namespace Modules\Search\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Library\Models\Article;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\Search\Models\SearchDocument;
use Modules\Search\Support\SearchText;

class SearchDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        SearchDocument::query()->forceDelete();

        Question::query()
            ->where('status', QuestionStatus::Published)
            ->orderBy('created_at')
            ->get()
            ->each(function (Question $question) use ($now): void {
                $stem = SearchText::normalize(SearchText::plain((string) $question->stem), 255);

                SearchDocument::query()->create([
                    'source_type' => Question::class,
                    'source_id' => $question->getKey(),
                    'scope' => 'qbank',
                    'type' => 'question',
                    'title' => $stem,
                    'summary' => SearchText::plain((string) ($question->explanation ?? '')),
                    'body' => SearchText::plain((string) $question->stem.' '.(string) $question->explanation),
                    'url' => route('qbank.index', ['q' => $stem]),
                    'is_free' => (bool) $question->is_free,
                    'is_published' => true,
                    'published_at' => $question->updated_at ?? $now,
                ]);
            });

        Article::query()
            ->where('is_published', true)
            ->orderBy('created_at')
            ->get()
            ->each(function (Article $article) use ($now): void {
                $title = SearchText::normalize(SearchText::plain((string) $article->title), 255);

                SearchDocument::query()->create([
                    'source_type' => Article::class,
                    'source_id' => $article->getKey(),
                    'scope' => 'library',
                    'type' => (string) $article->type,
                    'title' => $title,
                    'summary' => SearchText::plain((string) ($article->summary ?? '')),
                    'body' => SearchText::plain((string) $article->body),
                    'url' => route('search.index', ['q' => $title, 'type' => $article->type]),
                    'is_free' => (bool) $article->is_free,
                    'is_published' => true,
                    'published_at' => $article->published_at ?? $now,
                ]);
            });
    }
}
