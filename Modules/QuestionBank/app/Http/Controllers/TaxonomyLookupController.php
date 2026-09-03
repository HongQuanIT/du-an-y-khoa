<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\MedicalTaxonomy;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Tag;

/**
 * Lazy JSON lookups for blueprint / taxonomy / tag pickers (admin + learner).
 */
final class TaxonomyLookupController extends Controller
{
    public function blueprints(Request $request): JsonResponse
    {
        $items = Blueprint::query()
            ->where('status', TaxonomyStatus::Active)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(function ($builder) use ($term): void {
                    $builder->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->limit(50)
            ->get(['id', 'name', 'slug', 'code']);

        return response()->json(['data' => $items]);
    }

    public function blueprintSections(Request $request, Blueprint $blueprint): JsonResponse
    {
        $items = $blueprint->sections()
            ->where('status', TaxonomyStatus::Active)
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'blueprint_id', 'name', 'slug']);

        return response()->json(['data' => $items]);
    }

    public function coreClinicalTopics(Request $request, BlueprintSection $section): JsonResponse
    {
        $items = $section->coreClinicalTopics()
            ->where('status', TaxonomyStatus::Active)
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'blueprint_section_id', 'name', 'slug']);

        return response()->json(['data' => $items]);
    }

    public function searchCoreClinicalTopics(Request $request): JsonResponse
    {
        $query = CoreClinicalTopic::query()
            ->where('status', TaxonomyStatus::Active)
            ->with(['section:id,blueprint_id,name,sort_order']);

        if ($request->filled('blueprint_id')) {
            $blueprintId = (int) $request->query('blueprint_id');
            $query->whereHas('section', fn ($builder) => $builder->where('blueprint_id', $blueprintId));
        }

        if ($request->filled('blueprint_section_id')) {
            $query->where('blueprint_section_id', (int) $request->query('blueprint_section_id'));
        }

        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->query('q')).'%';
            $query->where('name', 'like', $term);
        }

        // The learner picker shows the complete curated catalog (currently 128),
        // not just the first page when it opens without a search term.
        $items = $query->limit(200)->get(['id', 'blueprint_section_id', 'name', 'slug', 'sort_order'])
            ->sortBy(fn (CoreClinicalTopic $topic): string => sprintf(
                '%05d:%05d:%s',
                $topic->section?->sort_order ?? PHP_INT_MAX,
                $topic->sort_order,
                $topic->name,
            ))
            ->map(fn (CoreClinicalTopic $topic): array => [
                'id' => $topic->id,
                'blueprint_section_id' => $topic->blueprint_section_id,
                'name' => $topic->name,
                'slug' => $topic->slug,
                'section_name' => $topic->section?->name,
                'section_sort_order' => $topic->section?->sort_order,
            ]);

        return response()->json(['data' => $items]);
    }

    public function medicalTaxonomyNodes(Request $request): JsonResponse
    {
        $parentId = $request->filled('parent_id') ? (int) $request->query('parent_id') : null;
        $includeDescendants = $request->boolean('include_descendants');
        $canonical = MedicalTaxonomy::canonical();

        $query = MedicalTaxonomyNode::query()
            ->where('status', TaxonomyStatus::Active)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($canonical !== null) {
            $query->where('medical_taxonomy_id', $canonical->id);
        }

        $types = [];
        if ($request->filled('node_type')) {
            $types = collect(explode(',', (string) $request->query('node_type')))
                ->map(fn (string $type): string => trim($type))
                ->filter()
                ->values()
                ->all();

            if ($types !== []) {
                $query->whereIn('node_type', $types);
            }
        }

        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->query('q')).'%';
            $query->where('name', 'like', $term)->limit(50);
        } elseif ($types !== []) {
            $query->limit(100);
        } elseif ($includeDescendants) {
            $query->limit(200);
        } else {
            $query->where('parent_id', $parentId)->limit(100);
        }

        $items = $query->get(['id', 'parent_id', 'medical_taxonomy_id', 'name', 'slug', 'node_type'])
            ->map(fn (MedicalTaxonomyNode $node): array => [
                'id' => $node->id,
                'parent_id' => $node->parent_id,
                'medical_taxonomy_id' => $node->medical_taxonomy_id,
                'name' => $node->name,
                'slug' => $node->slug,
                'node_type' => $node->node_type,
                'has_children' => MedicalTaxonomyNode::query()
                    ->where('parent_id', $node->id)
                    ->where('status', TaxonomyStatus::Active)
                    ->exists(),
            ]);

        return response()->json(['data' => $items]);
    }

    public function tags(Request $request): JsonResponse
    {
        $items = Tag::query()
            ->where('status', TaxonomyStatus::Active)
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(function ($builder) use ($term): void {
                    $builder->where('name', 'like', $term)->orWhere('slug', 'like', $term);
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'slug', 'type']);

        return response()->json(['data' => $items]);
    }
}
