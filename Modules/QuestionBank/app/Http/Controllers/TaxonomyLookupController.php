<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Subject;
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

        $sectionIds = collect($request->query('section_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($sectionIds !== []) {
            $query->whereIn('blueprint_section_id', $sectionIds);
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

    public function organSystems(Request $request): JsonResponse
    {
        $items = OrganSystem::query()
            ->where('status', TaxonomyStatus::Active)
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'slug', 'code']);

        return response()->json(['data' => $items]);
    }

    public function subjects(Request $request): JsonResponse
    {
        $query = Subject::query()
            ->where('status', TaxonomyStatus::Active)
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $builder->where('name', 'like', $term);
            });

        if ($request->filled('organ_system_id')) {
            $organSystemId = (int) $request->query('organ_system_id');
            $subjectIds = DB::table('subject_organ_system')
                ->where('organ_system_id', $organSystemId)
                ->pluck('subject_id')
                ->all();
            $query->whereIn('id', $subjectIds);
        }

        $items = $query->orderBy('sort_order')->orderBy('name')->limit(300)
            ->get(['id', 'name', 'slug', 'code']);

        return response()->json(['data' => $items]);
    }

    public function lessons(Request $request): JsonResponse
    {
        $query = Lesson::query()
            ->where('status', TaxonomyStatus::Active)
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $builder->where('name', 'like', $term);
            });

        if ($request->filled('subject_id')) {
            $subjectId = (int) $request->query('subject_id');
            $lessonIds = DB::table('lesson_subject')
                ->where('subject_id', $subjectId)
                ->pluck('lesson_id')
                ->all();
            $query->whereIn('id', $lessonIds);
        }

        if ($request->filled('organ_system_id')) {
            $organSystemId = (int) $request->query('organ_system_id');
            $subjectIds = DB::table('subject_organ_system')
                ->where('organ_system_id', $organSystemId)
                ->pluck('subject_id')
                ->all();
            $lessonIds = DB::table('lesson_subject')
                ->whereIn('subject_id', $subjectIds)
                ->pluck('lesson_id')
                ->all();
            $query->whereIn('id', $lessonIds);
        }

        $limit = $request->filled('q') ? 50 : 300;

        $items = $query
            ->with([
                'subjects:id,name',
                'subjects.organSystems:id,name',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'code'])
            ->map(fn (Lesson $lesson): array => [
                'id' => $lesson->id,
                'name' => $lesson->name,
                'slug' => $lesson->slug,
                'code' => $lesson->code,
                'subject_names' => $lesson->subjects->pluck('name')->unique()->values()->all(),
                'organ_system_names' => $lesson->subjects
                    ->flatMap(fn (Subject $subject) => $subject->organSystems->pluck('name'))
                    ->unique()
                    ->values()
                    ->all(),
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
