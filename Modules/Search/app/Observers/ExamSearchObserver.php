<?php

declare(strict_types=1);

namespace Modules\Search\Observers;

use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\Search\Models\SearchDocument;
use Modules\Search\Support\SearchText;

/**
 * Keeps SearchDocument in sync whenever an Exam is created, updated, or deleted.
 */
final class ExamSearchObserver
{
    public function saved(Exam $exam): void
    {
        if ($exam->status === ExamStatus::Published) {
            $title = SearchText::normalize(SearchText::plain((string) $exam->title), 255);
            $description = SearchText::plain((string) ($exam->description ?? ''));

            SearchDocument::query()->updateOrCreate(
                [
                    'source_type' => Exam::class,
                    'source_id' => $exam->getKey(),
                ],
                [
                    'scope' => 'exam',
                    'type' => 'exam',
                    'title' => $title,
                    'summary' => $description,
                    'body' => trim($title.' '.$description),
                    'url' => route('exam.index'),
                    'is_free' => false,
                    'is_published' => true,
                    'published_at' => $exam->updated_at ?? now(),
                ]
            );
        } else {
            SearchDocument::query()
                ->where('source_type', Exam::class)
                ->where('source_id', $exam->getKey())
                ->delete();
        }
    }

    public function deleted(Exam $exam): void
    {
        SearchDocument::query()
            ->where('source_type', Exam::class)
            ->where('source_id', $exam->getKey())
            ->delete();
    }
}
