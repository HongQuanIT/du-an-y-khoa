<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Question;

/**
 * Shared question list filters for API, admin, and session selection.
 *
 * Content axis: bài học (lessons) → môn học (subjects) → hệ cơ quan (organ_systems).
 * Questions attach only to lessons; subject/organ-system filters resolve down to
 * lessons via the lesson_subject / subject_organ_system pivots.
 *
 * Blueprint / core clinical topics are a separate exam matrix projected onto
 * lessons via core_topic_lessons and/or tags via core_topic_tags — questions
 * never require a direct question↔CCT pivot.
 */
final class QuestionFilterBuilder
{
    /**
     * @param  list<int>  $coreClinicalTopicIds
     * @param  list<int>  $organSystemIds
     * @param  list<int>  $subjectIds
     * @param  list<int>  $lessonIds
     * @param  list<int>  $tagIds
     */
    public function apply(
        Builder $query,
        ?int $blueprintId = null,
        ?int $blueprintSectionId = null,
        array $coreClinicalTopicIds = [],
        array $organSystemIds = [],
        array $subjectIds = [],
        array $lessonIds = [],
        array $tagIds = [],
        ?string $difficulty = null,
    ): Builder {
        if ($this->hasBlueprintFilter($blueprintId, $blueprintSectionId, $coreClinicalTopicIds)) {
            $this->applyBlueprintViaMapping(
                $query,
                $blueprintId,
                $blueprintSectionId,
                $coreClinicalTopicIds,
            );
        }

        if ($this->hasContentFilter($organSystemIds, $subjectIds, $lessonIds)) {
            $resolvedLessonIds = $this->resolveContentLessonIds($organSystemIds, $subjectIds, $lessonIds);

            if ($resolvedLessonIds === []) {
                return $query->whereRaw('0 = 1');
            }

            $query->whereHas(
                'lessons',
                fn (Builder $lessons) => $lessons->whereIn('lessons.id', $resolvedLessonIds),
            );
        }

        if ($tagIds !== []) {
            $query->whereHas(
                'tags',
                fn (Builder $tags) => $tags->whereIn('tags.id', $tagIds),
            );
        }

        if ($difficulty !== null && $difficulty !== '') {
            $query->where('difficulty', $difficulty);
        }

        return $query;
    }

    /**
     * Restrict questions that match a core clinical topic through lesson or tag mapping.
     */
    public function whereMatchesCoreClinicalTopic(Builder $query, int $coreClinicalTopicId): Builder
    {
        return $this->applyBlueprintViaMapping(
            $query,
            blueprintId: null,
            blueprintSectionId: null,
            coreClinicalTopicIds: [$coreClinicalTopicId],
        );
    }

    /**
     * Combined lesson id set from organ-system, subject and direct lesson filters.
     *
     * @param  list<int>  $organSystemIds
     * @param  list<int>  $subjectIds
     * @param  list<int>  $lessonIds
     * @return list<int>
     */
    public function resolveContentLessonIds(
        array $organSystemIds = [],
        array $subjectIds = [],
        array $lessonIds = [],
    ): array {
        return collect($this->normalizeIds($lessonIds))
            ->merge($this->lessonIdsForSubjects($subjectIds))
            ->merge($this->lessonIdsForOrganSystems($organSystemIds))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $subjectIds
     * @return list<int>
     */
    public function lessonIdsForSubjects(array $subjectIds): array
    {
        $subjectIds = $this->normalizeIds($subjectIds);
        if ($subjectIds === []) {
            return [];
        }

        return DB::table('lesson_subject')
            ->whereIn('subject_id', $subjectIds)
            ->pluck('lesson_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $organSystemIds
     * @return list<int>
     */
    public function lessonIdsForOrganSystems(array $organSystemIds): array
    {
        $organSystemIds = $this->normalizeIds($organSystemIds);
        if ($organSystemIds === []) {
            return [];
        }

        $subjectIds = DB::table('subject_organ_system')
            ->whereIn('organ_system_id', $organSystemIds)
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->lessonIdsForSubjects($subjectIds);
    }

    /**
     * @param  list<int>  $subjectIds
     * @return list<int>
     */
    public function subjectIdsForOrganSystems(array $organSystemIds): array
    {
        $organSystemIds = $this->normalizeIds($organSystemIds);
        if ($organSystemIds === []) {
            return [];
        }

        return DB::table('subject_organ_system')
            ->whereIn('organ_system_id', $organSystemIds)
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Lesson ids mapped from blueprint / section / CCT filters.
     *
     * @param  list<int>  $coreClinicalTopicIds
     * @return list<int>
     */
    public function mappedLessonIdsForBlueprint(
        ?int $blueprintId = null,
        ?int $blueprintSectionId = null,
        array $coreClinicalTopicIds = [],
    ): array {
        $topicIds = $this->resolveCoreTopicIds($blueprintId, $blueprintSectionId, $coreClinicalTopicIds);
        if ($topicIds === []) {
            return [];
        }

        return DB::table('core_topic_lessons')
            ->whereIn('core_clinical_topic_id', $topicIds)
            ->pluck('lesson_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Tag IDs mapped from blueprint / section / CCT filters.
     *
     * @param  list<int>  $coreClinicalTopicIds
     * @return list<int>
     */
    public function mappedTagIdsForBlueprint(
        ?int $blueprintId = null,
        ?int $blueprintSectionId = null,
        array $coreClinicalTopicIds = [],
    ): array {
        $topicIds = $this->resolveCoreTopicIds($blueprintId, $blueprintSectionId, $coreClinicalTopicIds);
        if ($topicIds === []) {
            return [];
        }

        return DB::table('core_topic_tags')
            ->whereIn('core_clinical_topic_id', $topicIds)
            ->pluck('tag_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Per-blueprint content scope for learner/admin filter UIs.
     *
     * @return array<int, array{lessonIds: list<int>, subjectIds: list<int>, organSystemIds: list<int>}>
     */
    public function taxonomyScopesForBlueprints(iterable $blueprintIds): array
    {
        $scopes = [];

        foreach ($blueprintIds as $blueprintId) {
            $id = (int) $blueprintId;
            $lessonIds = $this->mappedLessonIdsForBlueprint(blueprintId: $id);

            if ($lessonIds === []) {
                $scopes[$id] = ['lessonIds' => [], 'subjectIds' => [], 'organSystemIds' => []];

                continue;
            }

            $subjectIds = DB::table('lesson_subject')
                ->whereIn('lesson_id', $lessonIds)
                ->pluck('subject_id')
                ->map(fn ($sid): int => (int) $sid)
                ->unique()
                ->values()
                ->all();

            $organSystemIds = $subjectIds === []
                ? []
                : DB::table('subject_organ_system')
                    ->whereIn('subject_id', $subjectIds)
                    ->pluck('organ_system_id')
                    ->map(fn ($oid): int => (int) $oid)
                    ->unique()
                    ->values()
                    ->all();

            $scopes[$id] = [
                'lessonIds' => $lessonIds,
                'subjectIds' => $subjectIds,
                'organSystemIds' => $organSystemIds,
            ];
        }

        return $scopes;
    }

    /**
     * Infer CCT IDs for a question from its lessons.
     *
     * @param  list<int>  $lessonIds
     * @return list<int>
     */
    public function inferredCoreClinicalTopicIds(array $lessonIds): array
    {
        $lessonIds = $this->normalizeIds($lessonIds);
        if ($lessonIds === []) {
            return [];
        }

        return DB::table('core_topic_lessons')
            ->whereIn('lesson_id', $lessonIds)
            ->orderBy('core_clinical_topic_id')
            ->pluck('core_clinical_topic_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $tagIds
     * @return list<int>
     */
    public function inferredCoreClinicalTopicIdsFromTags(array $tagIds): array
    {
        $tagIds = $this->normalizeIds($tagIds);
        if ($tagIds === []) {
            return [];
        }

        return DB::table('core_topic_tags')
            ->whereIn('tag_id', $tagIds)
            ->orderBy('core_clinical_topic_id')
            ->pluck('core_clinical_topic_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $lessonIds
     * @return Collection<int, CoreClinicalTopic>
     */
    public function inferredCoreClinicalTopics(array $lessonIds): Collection
    {
        $ids = $this->inferredCoreClinicalTopicIds($lessonIds);
        if ($ids === []) {
            return collect();
        }

        return CoreClinicalTopic::query()
            ->with(['section:id,name,blueprint_id'])
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, CoreClinicalTopic>
     */
    public function inferredCoreClinicalTopicsForQuestion(Question $question): Collection
    {
        $lessonIds = ($question->relationLoaded('lessons')
            ? $question->lessons->pluck('id')
            : $question->lessons()->pluck('lessons.id'))
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $tagIds = ($question->relationLoaded('tags')
            ? $question->tags->pluck('id')
            : $question->tags()->pluck('tags.id'))
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $ids = collect($this->inferredCoreClinicalTopicIds($lessonIds))
            ->merge($this->inferredCoreClinicalTopicIdsFromTags($tagIds))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return CoreClinicalTopic::query()
            ->with(['section:id,name,blueprint_id'])
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<int>  $organSystemIds
     * @param  list<int>  $subjectIds
     * @param  list<int>  $lessonIds
     */
    private function hasContentFilter(array $organSystemIds, array $subjectIds, array $lessonIds): bool
    {
        return $organSystemIds !== [] || $subjectIds !== [] || $lessonIds !== [];
    }

    /**
     * @param  list<int>  $coreClinicalTopicIds
     */
    private function hasBlueprintFilter(
        ?int $blueprintId,
        ?int $blueprintSectionId,
        array $coreClinicalTopicIds,
    ): bool {
        return $blueprintId !== null
            || $blueprintSectionId !== null
            || $coreClinicalTopicIds !== [];
    }

    /**
     * @param  list<int>  $coreClinicalTopicIds
     * @return list<int>
     */
    private function resolveCoreTopicIds(
        ?int $blueprintId,
        ?int $blueprintSectionId,
        array $coreClinicalTopicIds,
    ): array {
        if (! $this->hasBlueprintFilter($blueprintId, $blueprintSectionId, $coreClinicalTopicIds)) {
            return [];
        }

        $topicQuery = CoreClinicalTopic::query()->select('core_clinical_topics.id');

        if ($blueprintId !== null) {
            $topicQuery->whereHas(
                'section',
                fn (Builder $sections) => $sections->where('blueprint_id', $blueprintId),
            );
        }

        if ($blueprintSectionId !== null) {
            $topicQuery->where('blueprint_section_id', $blueprintSectionId);
        }

        if ($coreClinicalTopicIds !== []) {
            $topicQuery->whereIn('core_clinical_topics.id', $coreClinicalTopicIds);
        }

        return $topicQuery->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  list<int>  $coreClinicalTopicIds
     */
    private function applyBlueprintViaMapping(
        Builder $query,
        ?int $blueprintId,
        ?int $blueprintSectionId,
        array $coreClinicalTopicIds,
    ): Builder {
        $mappedLessons = $this->mappedLessonIdsForBlueprint(
            $blueprintId,
            $blueprintSectionId,
            $coreClinicalTopicIds,
        );
        $mappedTags = $this->mappedTagIdsForBlueprint(
            $blueprintId,
            $blueprintSectionId,
            $coreClinicalTopicIds,
        );

        if ($mappedLessons === [] && $mappedTags === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $builder) use ($mappedLessons, $mappedTags): void {
            if ($mappedLessons !== []) {
                $builder->whereHas(
                    'lessons',
                    fn (Builder $lessons) => $lessons->whereIn('lessons.id', $mappedLessons),
                );
            }

            if ($mappedTags !== []) {
                $method = $mappedLessons !== [] ? 'orWhereHas' : 'whereHas';
                $builder->{$method}(
                    'tags',
                    fn (Builder $tags) => $tags->whereIn('tags.id', $mappedTags),
                );
            }
        });
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
