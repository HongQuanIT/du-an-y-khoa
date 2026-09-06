<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;

/**
 * Shared question list filters for API, admin, and session selection.
 *
 * Authoring axis: medical taxonomy (+ tags).
 * Blueprint / core clinical topics are projections via
 * core_topic_medical_taxonomy_nodes and/or core_topic_tags —
 * never require a direct question↔CCT pivot.
 */
final class QuestionFilterBuilder
{
    /**
     * @param  list<int>  $coreClinicalTopicIds
     * @param  list<int>  $medicalTaxonomyNodeIds
     * @param  list<int>  $tagIds
     */
    public function apply(
        Builder $query,
        ?int $blueprintId = null,
        ?int $blueprintSectionId = null,
        array $coreClinicalTopicIds = [],
        array $medicalTaxonomyNodeIds = [],
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

        $expandedMedicalNodeIds = $this->expandMedicalTaxonomyNodes($medicalTaxonomyNodeIds);
        if ($expandedMedicalNodeIds !== []) {
            $query->whereHas(
                'medicalTaxonomyNodes',
                fn (Builder $nodes) => $nodes->whereIn('medical_taxonomy_nodes.id', $expandedMedicalNodeIds),
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
     * Restrict questions that match a core clinical topic through medical-taxonomy or tag mapping.
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
     * Medical node IDs mapped from blueprint / section / CCT filters (descendants expanded).
     *
     * @param  list<int>  $coreClinicalTopicIds
     * @return list<int>
     */
    public function mappedMedicalNodeIdsForBlueprint(
        ?int $blueprintId = null,
        ?int $blueprintSectionId = null,
        array $coreClinicalTopicIds = [],
    ): array {
        $topicIds = $this->resolveCoreTopicIds($blueprintId, $blueprintSectionId, $coreClinicalTopicIds);
        if ($topicIds === []) {
            return [];
        }

        $mapped = DB::table('core_topic_medical_taxonomy_nodes')
            ->whereIn('core_clinical_topic_id', $topicIds)
            ->pluck('medical_taxonomy_node_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->expandMedicalTaxonomyNodes($mapped);
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
     * Full medical-taxonomy node set for a blueprint UI scope:
     * mapped nodes + descendants (for question matching) + ancestors (so system/specialty parents appear).
     *
     * @param  list<int>  $coreClinicalTopicIds
     * @return list<int>
     */
    public function relatedMedicalNodeIdsForBlueprint(
        ?int $blueprintId = null,
        ?int $blueprintSectionId = null,
        array $coreClinicalTopicIds = [],
    ): array {
        if (! $this->hasBlueprintFilter($blueprintId, $blueprintSectionId, $coreClinicalTopicIds)) {
            return [];
        }

        $topicIds = $this->resolveCoreTopicIds($blueprintId, $blueprintSectionId, $coreClinicalTopicIds);
        if ($topicIds === []) {
            return [];
        }

        $mapped = DB::table('core_topic_medical_taxonomy_nodes')
            ->whereIn('core_clinical_topic_id', $topicIds)
            ->pluck('medical_taxonomy_node_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($mapped === []) {
            return [];
        }

        $descendants = $this->expandMedicalTaxonomyNodes($mapped);
        $ancestors = $this->expandWithAncestors($mapped);

        return collect($descendants)
            ->merge($ancestors)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{nodeIds: list<int>, systemIds: list<int>, specialtyIds: list<int>}>
     */
    public function taxonomyScopesForBlueprints(iterable $blueprintIds): array
    {
        $scopes = [];

        foreach ($blueprintIds as $blueprintId) {
            $id = (int) $blueprintId;
            $nodeIds = $this->relatedMedicalNodeIdsForBlueprint(blueprintId: $id);
            if ($nodeIds === []) {
                $scopes[$id] = [
                    'nodeIds' => [],
                    'systemIds' => [],
                    'specialtyIds' => [],
                ];

                continue;
            }

            $typed = MedicalTaxonomyNode::query()
                ->whereIn('id', $nodeIds)
                ->whereIn('node_type', ['system', 'specialty'])
                ->get(['id', 'node_type']);

            $scopes[$id] = [
                'nodeIds' => $nodeIds,
                'systemIds' => $typed
                    ->where('node_type', 'system')
                    ->pluck('id')
                    ->map(fn ($nodeId): int => (int) $nodeId)
                    ->values()
                    ->all(),
                'specialtyIds' => $typed
                    ->where('node_type', 'specialty')
                    ->pluck('id')
                    ->map(fn ($nodeId): int => (int) $nodeId)
                    ->values()
                    ->all(),
            ];
        }

        return $scopes;
    }

    /**
     * Infer CCT IDs for a question from its medical taxonomy nodes (+ ancestors).
     *
     * @param  list<int>  $medicalTaxonomyNodeIds
     * @return list<int>
     */
    public function inferredCoreClinicalTopicIds(array $medicalTaxonomyNodeIds): array
    {
        $matchNodeIds = $this->expandWithAncestors($medicalTaxonomyNodeIds);
        if ($matchNodeIds === []) {
            return [];
        }

        return CoreClinicalTopic::query()
            ->whereHas(
                'medicalTaxonomyNodes',
                fn (Builder $nodes) => $nodes->whereIn('medical_taxonomy_nodes.id', $matchNodeIds),
            )
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $tagIds
     * @return list<int>
     */
    public function inferredCoreClinicalTopicIdsFromTags(array $tagIds): array
    {
        $tagIds = collect($tagIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();
        if ($tagIds === []) {
            return [];
        }

        return CoreClinicalTopic::query()
            ->whereHas(
                'tags',
                fn (Builder $tags) => $tags->whereIn('tags.id', $tagIds),
            )
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $medicalTaxonomyNodeIds
     * @return Collection<int, CoreClinicalTopic>
     */
    public function inferredCoreClinicalTopics(array $medicalTaxonomyNodeIds): Collection
    {
        $ids = $this->inferredCoreClinicalTopicIds($medicalTaxonomyNodeIds);
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
        $nodeIds = ($question->relationLoaded('medicalTaxonomyNodes')
            ? $question->medicalTaxonomyNodes->pluck('id')
            : $question->medicalTaxonomyNodes()->pluck('medical_taxonomy_nodes.id'))
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $tagIds = ($question->relationLoaded('tags')
            ? $question->tags->pluck('id')
            : $question->tags()->pluck('tags.id'))
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $ids = collect($this->inferredCoreClinicalTopicIds($nodeIds))
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
     * @param  list<int>  $nodeIds
     * @return list<int>
     */
    public function expandMedicalTaxonomyNodes(array $nodeIds): array
    {
        $nodeIds = collect($nodeIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();
        if ($nodeIds === []) {
            return [];
        }

        $all = collect($nodeIds);
        $frontier = collect($nodeIds);

        while ($frontier->isNotEmpty()) {
            $children = MedicalTaxonomyNode::query()
                ->whereIn('parent_id', $frontier->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

            $frontier = $children->diff($all)->values();
            $all = $all->merge($frontier)->unique()->values();
        }

        return $all->all();
    }

    /**
     * Include each node and all ancestors (for CCT inference from leaf tags).
     *
     * @param  list<int>  $nodeIds
     * @return list<int>
     */
    public function expandWithAncestors(array $nodeIds): array
    {
        $nodeIds = collect($nodeIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();
        if ($nodeIds === []) {
            return [];
        }

        $all = collect($nodeIds);
        $frontier = collect($nodeIds);

        while ($frontier->isNotEmpty()) {
            $parents = MedicalTaxonomyNode::query()
                ->whereIn('id', $frontier->all())
                ->whereNotNull('parent_id')
                ->pluck('parent_id')
                ->map(fn ($id): int => (int) $id)
                ->filter();

            $frontier = $parents->diff($all)->values();
            $all = $all->merge($frontier)->unique()->values();
        }

        return $all->all();
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
        $expandedNodes = $this->mappedMedicalNodeIdsForBlueprint(
            $blueprintId,
            $blueprintSectionId,
            $coreClinicalTopicIds,
        );
        $mappedTags = $this->mappedTagIdsForBlueprint(
            $blueprintId,
            $blueprintSectionId,
            $coreClinicalTopicIds,
        );

        if ($expandedNodes === [] && $mappedTags === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $builder) use ($expandedNodes, $mappedTags): void {
            if ($expandedNodes !== []) {
                $builder->whereHas(
                    'medicalTaxonomyNodes',
                    fn (Builder $nodes) => $nodes->whereIn('medical_taxonomy_nodes.id', $expandedNodes),
                );
            }

            if ($mappedTags !== []) {
                $method = $expandedNodes !== [] ? 'orWhereHas' : 'whereHas';
                $builder->{$method}(
                    'tags',
                    fn (Builder $tags) => $tags->whereIn('tags.id', $mappedTags),
                );
            }
        });
    }
}
