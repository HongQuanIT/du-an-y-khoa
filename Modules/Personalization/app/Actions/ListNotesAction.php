<?php

declare(strict_types=1);

namespace Modules\Personalization\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Html\SafeHtml;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Personalization\Models\Note;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Subject;

final class ListNotesAction
{
    use AsAction;

    private const MAX_GROUPED = 500;

    /**
     * @param  array{
     *   q?: string|null,
     *   view?: string|null,
     *   per_page?: int
     * }  $filters
     * @return array{
     *   mode: 'grouped'|'flat',
     *   total: int,
     *   groups?: list<array<string, mixed>>,
     *   notes?: LengthAwarePaginator<int, array<string, mixed>>
     * }
     */
    public function handle(User $user, array $filters = []): array
    {
        $view = $filters['view'] ?? 'subject';
        if (! in_array($view, ['subject', 'lesson', 'notebook'], true)) {
            $view = 'subject';
        }

        $query = Note::query()->where('user_id', $user->getKey());

        if ($view === 'notebook') {
            $query->whereNull('notable_type')->whereNull('notable_id');
        } else {
            $query->where('notable_type', Note::TYPE_QUESTION);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $this->applySearch($query, $q, $view === 'notebook');
        }

        if ($view === 'notebook') {
            $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 20)));
            /** @var LengthAwarePaginator<int, Note> $paginator */
            $paginator = (clone $query)->orderByDesc('updated_at')->paginate($perPage)->withQueryString();
            $questions = collect();

            return [
                'mode' => 'flat',
                'total' => $paginator->total(),
                'notes' => $paginator->through(fn (Note $note): array => $this->serialize($note, $questions)),
            ];
        }

        /** @var Collection<int, Note> $notes */
        $notes = (clone $query)
            ->orderByDesc('updated_at')
            ->limit(self::MAX_GROUPED)
            ->get();

        $questions = $this->loadQuestions($notes);
        $serialized = $notes->map(fn (Note $note): array => $this->serialize($note, $questions));

        return [
            'mode' => 'grouped',
            'total' => $serialized->count(),
            'groups' => $view === 'lesson'
                ? $this->groupByLesson($serialized)
                : $this->groupBySubject($serialized),
        ];
    }

    /**
     * Notebook: body only. Question views: body OR lesson name OR subject name.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Note>  $query
     */
    private function applySearch($query, string $term, bool $notebookOnly): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        if ($notebookOnly) {
            $query->where('body', 'like', $like);

            return;
        }

        $query->where(function ($outer) use ($like): void {
            $outer->where('body', 'like', $like)
                ->orWhereExists(function ($sub) use ($like): void {
                    $sub->selectRaw('1')
                        ->from('questions')
                        ->join('question_lesson', 'question_lesson.question_id', '=', 'questions.id')
                        ->join('lessons', 'lessons.id', '=', 'question_lesson.lesson_id')
                        ->whereColumn('questions.id', 'notes.notable_id')
                        ->where(function ($match) use ($like): void {
                            $match->where('lessons.name', 'like', $like)
                                ->orWhereExists(function ($subjectSub) use ($like): void {
                                    $subjectSub->selectRaw('1')
                                        ->from('lesson_subject')
                                        ->join('subjects', 'subjects.id', '=', 'lesson_subject.subject_id')
                                        ->whereColumn('lesson_subject.lesson_id', 'lessons.id')
                                        ->where('subjects.name', 'like', $like);
                                });
                        });
                });
        });
    }

    /**
     * @param  Collection<int, Note>  $notes
     * @return Collection<string, Question>
     */
    private function loadQuestions(Collection $notes): Collection
    {
        $questionIds = $notes
            ->filter(fn (Note $note): bool => $note->notable_type === Note::TYPE_QUESTION)
            ->pluck('notable_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        if ($questionIds === []) {
            return collect();
        }

        return Question::query()
            ->with(['lessons' => fn ($q) => $q->orderBy('lessons.sort_order')->orderBy('lessons.name')])
            ->with(['lessons.subjects' => fn ($q) => $q->orderBy('subjects.sort_order')->orderBy('subjects.name')])
            ->whereIn('id', $questionIds)
            ->get()
            ->keyBy(fn (Question $question): string => (string) $question->getKey());
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $notes
     * @return list<array<string, mixed>>
     */
    private function groupBySubject(Collection $notes): array
    {
        return $this->groupByDimension($notes, 'subjects', 'Chưa gắn môn học');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $notes
     * @return list<array<string, mixed>>
     */
    private function groupByLesson(Collection $notes): array
    {
        return $this->groupByDimension($notes, 'lessons', 'Chưa gắn bài học');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $notes
     * @return list<array<string, mixed>>
     */
    private function groupByDimension(
        Collection $notes,
        string $dimension,
        string $uncategorizedTitle,
    ): array {
        $buckets = [];
        $uncategorized = [];

        foreach ($notes as $note) {
            $items = $note[$dimension] ?? [];
            if ($items === []) {
                $uncategorized[] = $note;
                continue;
            }

            $primary = $items[0];
            $key = $dimension.':'.$primary['id'];
            $buckets[$key] ??= [
                'key' => $key,
                'title' => $primary['name'],
                'sort_order' => (int) ($primary['sort_order'] ?? 9999),
                'notes' => [],
            ];
            $buckets[$key]['notes'][] = $note;
        }

        $groups = collect($buckets)
            ->sortBy([
                ['sort_order', 'asc'],
                ['title', 'asc'],
            ])
            ->map(function (array $bucket): array {
                $items = $bucket['notes'];
                usort($items, static fn (array $a, array $b): int => strcmp(
                    (string) ($b['updated_at'] ?? ''),
                    (string) ($a['updated_at'] ?? ''),
                ));

                return [
                    'key' => $bucket['key'],
                    'title' => $bucket['title'],
                    'count' => count($items),
                    'notes' => array_values($items),
                ];
            })
            ->values();

        if ($uncategorized !== []) {
            usort($uncategorized, static fn (array $a, array $b): int => strcmp(
                (string) ($b['updated_at'] ?? ''),
                (string) ($a['updated_at'] ?? ''),
            ));
            $groups->push([
                'key' => 'uncategorized',
                'title' => $uncategorizedTitle,
                'count' => count($uncategorized),
                'notes' => array_values($uncategorized),
            ]);
        }

        return $groups->all();
    }

    /**
     * @param  Collection<string, Question>  $questions
     * @return array<string, mixed>
     */
    private function serialize(Note $note, Collection $questions): array
    {
        $sourceTitle = null;
        $orphan = false;
        $lessons = [];
        $subjects = [];

        if ($note->notable_type === Note::TYPE_QUESTION) {
            $question = $questions->get((string) $note->notable_id);
            if ($question instanceof Question) {
                $sourceTitle = Str::limit(SafeHtml::plainText((string) $question->stem), 140);
                $lessons = $question->lessons->map(fn (Lesson $lesson): array => [
                    'id' => $lesson->id,
                    'name' => $lesson->name,
                    'sort_order' => (int) $lesson->sort_order,
                ])->values()->all();
                $subjects = $question->inferredSubjects()
                    ->sortBy([
                        fn (Subject $subject): int => (int) $subject->sort_order,
                        fn (Subject $subject): string => (string) $subject->name,
                    ])
                    ->values()
                    ->map(fn (Subject $subject): array => [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'sort_order' => (int) $subject->sort_order,
                    ])
                    ->all();
            } else {
                $orphan = true;
                $sourceTitle = 'Câu hỏi đã gỡ';
            }
        } else {
            $sourceTitle = 'Sổ tay';
        }

        return [
            'id' => $note->id,
            'notable_type' => $note->notable_type,
            'notable_id' => $note->notable_id,
            'type_label' => $note->isFree() ? 'Sổ tay' : 'Câu hỏi',
            'body' => $note->body,
            'body_html' => $note->body_html,
            'color' => $note->color,
            'source_title' => $sourceTitle,
            'orphan' => $orphan,
            'lessons' => $lessons,
            'subjects' => $subjects,
            'updated_at' => $note->updated_at?->toIso8601String(),
            'updated_at_label' => $note->updated_at?->diffForHumans(),
        ];
    }
}
