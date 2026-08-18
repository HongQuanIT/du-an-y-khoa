<?php

namespace Modules\Search\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Models\Classroom;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
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

        Classroom::query()
            ->where('status', ClassroomStatus::Active)
            ->where('visibility', ClassroomVisibility::Public)
            ->orderBy('created_at')
            ->get()
            ->each(function (Classroom $classroom) use ($now): void {
                $title = SearchText::normalize(SearchText::plain((string) $classroom->title), 255);
                $description = SearchText::plain((string) ($classroom->description ?? ''));

                SearchDocument::query()->create([
                    'source_type' => Classroom::class,
                    'source_id' => $classroom->getKey(),
                    'scope' => 'classroom',
                    'type' => 'classroom',
                    'title' => $title,
                    'summary' => $description,
                    'body' => trim($title.' '.$description),
                    'url' => route('classroom.show', $classroom),
                    'is_free' => true,
                    'is_published' => true,
                    'published_at' => $classroom->updated_at ?? $now,
                ]);
            });

        Exam::query()
            ->where('is_published', true)
            ->where('status', ExamStatus::Published)
            ->orderBy('created_at')
            ->get()
            ->each(function (Exam $exam) use ($now): void {
                $title = SearchText::normalize(SearchText::plain((string) $exam->title), 255);
                $description = SearchText::plain((string) ($exam->description ?? ''));

                SearchDocument::query()->create([
                    'source_type' => Exam::class,
                    'source_id' => $exam->getKey(),
                    'scope' => 'exam',
                    'type' => 'exam',
                    'title' => $title,
                    'summary' => $description,
                    'body' => trim($title.' '.$description),
                    'url' => route('exam.index'),
                    'is_free' => false,
                    'is_published' => true,
                    'published_at' => $exam->updated_at ?? $now,
                ]);
            });
    }
}
