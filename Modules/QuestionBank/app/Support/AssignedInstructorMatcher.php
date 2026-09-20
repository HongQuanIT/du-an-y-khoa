<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use App\Models\User;
use App\Support\Enums\Role;
use App\Support\Enums\UserStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Models\Question;

/**
 * Route a question to instructors who share at least one subject with its lessons.
 */
final class AssignedInstructorMatcher
{
    /**
     * @param  list<int>  $lessonIds
     * @return list<int>
     */
    public function subjectIdsForLessons(array $lessonIds): array
    {
        $lessonIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $lessonIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($lessonIds === []) {
            return [];
        }

        return DB::table('lesson_subject')
            ->whereIn('lesson_id', $lessonIds)
            ->pluck('subject_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $lessonIds
     */
    public function instructorMatchesLessons(User $instructor, array $lessonIds): bool
    {
        if (! $instructor->hasRole(Role::Instructor->value)) {
            return false;
        }

        $subjects = $this->subjectIdsForLessons($lessonIds);
        if ($subjects === []) {
            return false;
        }

        return $instructor->instructorSubjects()
            ->whereIn('subjects.id', $subjects)
            ->exists();
    }

    public function instructorMatchesQuestion(User $instructor, Question $question): bool
    {
        $lessonIds = $question->lessons()
            ->pluck('lessons.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $this->instructorMatchesLessons($instructor, $lessonIds);
    }

    /**
     * @param  list<int>  $lessonIds
     * @return Collection<int, User>
     */
    public function eligibleInstructors(array $lessonIds): Collection
    {
        $subjects = $this->subjectIdsForLessons($lessonIds);
        if ($subjects === []) {
            return collect();
        }

        return User::query()
            ->role(Role::Instructor->value)
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', UserStatus::Active->value);
            })
            ->whereHas('instructorSubjects', fn ($query) => $query->whereIn('subjects.id', $subjects))
            ->with(['instructorSubjects' => fn ($query) => $query
                ->whereIn('subjects.id', $subjects)
                ->orderBy('name')
                ->select('subjects.id', 'subjects.name')])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
