<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Database\Eloquent\Builder;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;

/**
 * Shared question list filters for API, admin, and session selection.
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
        if ($blueprintId !== null) {
            $query->whereHas('coreClinicalTopics.section', fn (Builder $sections) => $sections
                ->where('blueprint_id', $blueprintId));
        }

        if ($blueprintSectionId !== null) {
            $query->whereHas('coreClinicalTopics', fn (Builder $topics) => $topics
                ->where('blueprint_section_id', $blueprintSectionId));
        }

        if ($coreClinicalTopicIds !== []) {
            $query->whereHas(
                'coreClinicalTopics',
                fn (Builder $topics) => $topics->whereIn('core_clinical_topics.id', $coreClinicalTopicIds),
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
}
