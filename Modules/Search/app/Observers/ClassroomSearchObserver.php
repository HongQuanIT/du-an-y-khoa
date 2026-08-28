<?php

declare(strict_types=1);

namespace Modules\Search\Observers;

use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Models\Classroom;
use Modules\Search\Models\SearchDocument;
use Modules\Search\Support\SearchText;

/**
 * Keeps SearchDocument in sync whenever a Classroom is created, updated, or deleted.
 */
final class ClassroomSearchObserver
{
    public function saved(Classroom $classroom): void
    {
        if ($classroom->status === ClassroomStatus::Active && $classroom->visibility === ClassroomVisibility::Public) {
            $title = SearchText::normalize(SearchText::plain((string) $classroom->title), 255);
            $description = SearchText::plain((string) ($classroom->description ?? ''));

            SearchDocument::syncSource(
                Classroom::class,
                $classroom->getKey(),
                [
                    'scope' => 'classroom',
                    'type' => 'classroom',
                    'title' => $title,
                    'summary' => $description,
                    'body' => trim($title.' '.$description),
                    'url' => route('classroom.show', $classroom),
                    'is_free' => true,
                    'is_published' => true,
                    'published_at' => $classroom->updated_at ?? now(),
                ],
            );
        } else {
            SearchDocument::query()
                ->where('source_type', Classroom::class)
                ->where('source_id', $classroom->getKey())
                ->delete();
        }
    }

    public function deleted(Classroom $classroom): void
    {
        SearchDocument::query()
            ->where('source_type', Classroom::class)
            ->where('source_id', $classroom->getKey())
            ->delete();
    }
}
