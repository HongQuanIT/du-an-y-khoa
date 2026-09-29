<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\PortalRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;

final class BlueprintController extends Controller
{
    public function index(): View
    {
        $this->authorizePermission('blueprint.view');

        $blueprints = Blueprint::query()
            ->withCount('sections')
            ->orderBy('sort_order')
            ->get();

        $coreTopicCounts = CoreClinicalTopic::query()
            ->join('blueprint_sections', 'blueprint_sections.id', '=', 'core_clinical_topics.blueprint_section_id')
            ->selectRaw('blueprint_sections.blueprint_id, COUNT(*) as total')
            ->groupBy('blueprint_sections.blueprint_id')
            ->pluck('total', 'blueprint_id');

        return view('admin::blueprints.index', [
            'blueprints' => $blueprints,
            'coreTopicCounts' => $coreTopicCounts,
            'canCreate' => $this->actor()->can('blueprint.create'),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('blueprint.create');

        return view('admin::blueprints.form', $this->formData(new Blueprint([
            'status' => TaxonomyStatus::Active,
            'sort_order' => 0,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission('blueprint.create');
        $data = $this->validatedBlueprint($request);
        $blueprint = Blueprint::query()->create($data);

        return redirect()->route(PortalRoute::content('blueprints.edit'), $blueprint)->with('status', 'Đã tạo ma trận đề thi.');
    }

    public function edit(Blueprint $blueprint): View
    {
        $this->authorizePermission('blueprint.update');
        $blueprint->load(['sections.coreClinicalTopics.lessons', 'sections.coreClinicalTopics.tags']);

        return view('admin::blueprints.form', $this->formData($blueprint));
    }

    public function update(Request $request, Blueprint $blueprint): RedirectResponse
    {
        $this->authorizePermission('blueprint.update');
        $blueprint->update($this->validatedBlueprint($request, $blueprint));

        return back()->with('status', 'Đã cập nhật ma trận đề thi.');
    }

    public function updateWeights(Request $request, Blueprint $blueprint): RedirectResponse|JsonResponse
    {
        $this->authorizePermission('blueprint.update');

        $data = $request->validate([
            'total_questions' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['required', 'integer'],
            'sections.*.weight_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sections.*.weight_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sections.*.topics' => ['nullable', 'array'],
            'sections.*.topics.*.id' => ['required', 'integer'],
            'sections.*.topics.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $sectionIds = $blueprint->sections()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $topicIdsBySection = CoreClinicalTopic::query()
            ->whereIn('blueprint_section_id', $sectionIds)
            ->get(['id', 'blueprint_section_id'])
            ->groupBy(fn (CoreClinicalTopic $topic): int => (int) $topic->blueprint_section_id)
            ->map(fn ($topics) => $topics->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->all();

        $sectionsPayload = $data['sections'] ?? [];

        foreach ($sectionsPayload as $index => $sectionPayload) {
            $sectionId = (int) $sectionPayload['id'];
            if (! in_array($sectionId, $sectionIds, true)) {
                throw ValidationException::withMessages([
                    "sections.{$index}.id" => 'Phần không thuộc ma trận này.',
                ]);
            }

            $this->assertWeightPair(
                $sectionPayload['weight_min'] ?? null,
                $sectionPayload['weight_max'] ?? null,
                "sections.{$index}",
            );

            foreach ($sectionPayload['topics'] ?? [] as $topicIndex => $topicPayload) {
                $topicId = (int) $topicPayload['id'];
                $allowedTopicIds = $topicIdsBySection[$sectionId] ?? [];
                if (! in_array($topicId, $allowedTopicIds, true)) {
                    throw ValidationException::withMessages([
                        "sections.{$index}.topics.{$topicIndex}.id" => 'Chủ đề không thuộc phần này.',
                    ]);
                }
            }
        }

        DB::transaction(function () use ($blueprint, $data, $sectionsPayload): void {
            $blueprint->update([
                'total_questions' => array_key_exists('total_questions', $data)
                    ? $data['total_questions']
                    : $blueprint->total_questions,
            ]);

            foreach ($sectionsPayload as $sectionPayload) {
                BlueprintSection::query()
                    ->where('blueprint_id', $blueprint->id)
                    ->where('id', (int) $sectionPayload['id'])
                    ->update([
                        'weight_min' => $this->nullableWeight($sectionPayload['weight_min'] ?? null),
                        'weight_max' => $this->nullableWeight($sectionPayload['weight_max'] ?? null),
                    ]);

                foreach ($sectionPayload['topics'] ?? [] as $topicPayload) {
                    CoreClinicalTopic::query()
                        ->where('blueprint_section_id', (int) $sectionPayload['id'])
                        ->where('id', (int) $topicPayload['id'])
                        ->update([
                            'weight' => $this->nullableWeight($topicPayload['weight'] ?? null),
                        ]);
                }
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Đã lưu cấu hình tỉ trọng ma trận.',
                'data' => [
                    'total_questions' => $blueprint->fresh()->total_questions,
                ],
            ]);
        }

        return back()->with('status', 'Đã lưu cấu hình tỉ trọng ma trận.');
    }

    public function destroy(Blueprint $blueprint): RedirectResponse
    {
        $this->authorizePermission('blueprint.delete');
        $blueprint->update(['status' => TaxonomyStatus::Inactive]);

        return redirect()->route(PortalRoute::content('blueprints.index'))->with('status', 'Đã vô hiệu hóa ma trận đề thi.');
    }

    public function storeSection(Request $request, Blueprint $blueprint): RedirectResponse
    {
        $this->authorizePermission('blueprint.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = $this->uniqueSectionSlug($blueprint, $data['slug'] ?? Str::slug($data['name']));

        $blueprint->sections()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'status' => TaxonomyStatus::Active,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('status', 'Đã thêm phần.');
    }

    public function destroySection(BlueprintSection $section): RedirectResponse
    {
        $this->authorizePermission('blueprint.delete');

        $section->delete();

        return back()->with('status', 'Đã xóa phần và các chủ đề lâm sàng bên trong.');
    }

    public function storeCoreTopic(Request $request, BlueprintSection $section): RedirectResponse
    {
        $this->authorizePermission('blueprint.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = $this->uniqueCoreTopicSlug($section, $data['slug'] ?? Str::slug($data['name']));

        $section->coreClinicalTopics()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'status' => TaxonomyStatus::Active,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('status', 'Đã thêm chủ đề lâm sàng.');
    }

    public function destroyCoreTopic(CoreClinicalTopic $topic): RedirectResponse
    {
        $this->authorizePermission('blueprint.delete');

        $topic->delete();

        return back()->with('status', 'Đã xóa chủ đề lâm sàng.');
    }

    public function syncCoreTopicMedicalNodes(Request $request, CoreClinicalTopic $topic): RedirectResponse|JsonResponse
    {
        $this->authorizePermission('blueprint.update');

        $data = $request->validate([
            'lessons' => ['nullable', 'array'],
            'lessons.*.id' => ['required', 'integer', 'exists:lessons,id'],
            'lessons.*.is_priority' => ['nullable', 'boolean'],
            'lesson_ids' => ['nullable', 'array'],
            'lesson_ids.*' => ['integer', 'exists:lessons,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $lessonSync = $this->normalizeLessonSyncPayload(
            $data['lessons'] ?? null,
            $data['lesson_ids'] ?? null,
        );

        $topic->lessons()->sync($lessonSync);
        $topic->tags()->sync($data['tag_ids'] ?? []);

        if ($request->expectsJson()) {
            $topic->load('lessons');

            return response()->json([
                'message' => 'Đã cập nhật liên kết bài học và tag cho chủ đề lâm sàng.',
                'data' => [
                    'lessons' => $topic->lessons->map(fn (Lesson $lesson): array => [
                        'id' => (int) $lesson->id,
                        'name' => $lesson->name,
                        'is_priority' => (bool) ($lesson->pivot->is_priority ?? true),
                    ])->values()->all(),
                    'lesson_ids' => $topic->lessons->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                    'tag_ids' => collect($data['tag_ids'] ?? [])
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all(),
                ],
            ]);
        }

        return back()->with('status', 'Đã cập nhật liên kết bài học và tag cho chủ đề lâm sàng.');
    }

    /**
     * @param  array<int, array{id: int|string, is_priority?: bool|int|string|null}>|null  $lessons
     * @param  array<int, int|string>|null  $lessonIds
     * @return array<int, array{is_priority: bool}>
     */
    private function normalizeLessonSyncPayload(?array $lessons, ?array $lessonIds): array
    {
        $sync = [];

        if (is_array($lessons) && $lessons !== []) {
            foreach ($lessons as $lesson) {
                $id = (int) ($lesson['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $sync[$id] = [
                    'is_priority' => array_key_exists('is_priority', $lesson)
                        ? $this->toBool($lesson['is_priority'], default: true)
                        : true,
                ];
            }

            return $sync;
        }

        foreach ($lessonIds ?? [] as $lessonId) {
            $id = (int) $lessonId;
            if ($id <= 0) {
                continue;
            }

            $sync[$id] = ['is_priority' => true];
        }

        return $sync;
    }

    private function toBool(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** @return array<string, mixed> */
    private function formData(Blueprint $blueprint): array
    {
        return [
            'blueprint' => $blueprint,
            'statuses' => TaxonomyStatus::cases(),
            'canUpdate' => $blueprint->exists
                ? $this->actor()->can('blueprint.update')
                : $this->actor()->can('blueprint.create'),
            'canDelete' => $blueprint->exists && $this->actor()->can('blueprint.delete'),
        ];
    }

    /** @return array<string, mixed> */
    private function validatedBlueprint(Request $request, ?Blueprint $blueprint = null): array
    {
        $uniqueCode = Rule::unique('blueprints', 'code');
        if ($blueprint !== null) {
            $uniqueCode = $uniqueCode->ignore($blueprint->id);
        }

        $code = trim((string) $request->input('code', ''));
        $request->merge(['code' => $code !== '' ? $code : null]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', $uniqueCode],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(TaxonomyStatus::values())],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        return [
            'name' => $data['name'],
            'slug' => $this->resolveBlueprintSlug($data['name'], $blueprint),
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'sort_order' => (int) $data['sort_order'],
        ];
    }

    private function resolveBlueprintSlug(string $name, ?Blueprint $blueprint): string
    {
        $existing = trim((string) ($blueprint?->slug ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        return $this->uniqueBlueprintSlug(Str::slug($name) !== '' ? Str::slug($name) : 'blueprint', $blueprint);
    }

    private function uniqueBlueprintSlug(string $slug, ?Blueprint $blueprint): string
    {
        $base = $slug !== '' ? $slug : 'blueprint';
        $candidate = $base;
        $suffix = 1;

        while (Blueprint::query()
            ->where('slug', $candidate)
            ->when($blueprint?->id, fn ($query, $id) => $query->where('id', '!=', $id))
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueSectionSlug(Blueprint $blueprint, string $slug): string
    {
        $base = Str::slug($slug !== '' ? $slug : 'section');
        $candidate = $base;
        $suffix = 1;

        while ($blueprint->sections()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueCoreTopicSlug(BlueprintSection $section, string $slug): string
    {
        $base = Str::slug($slug !== '' ? $slug : 'topic');
        $candidate = $base;
        $suffix = 1;

        while ($section->coreClinicalTopics()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function assertWeightPair(mixed $min, mixed $max, string $prefix): void
    {
        $minValue = $this->nullableWeight($min);
        $maxValue = $this->nullableWeight($max);

        if ($minValue !== null && $maxValue !== null && $minValue > $maxValue) {
            throw ValidationException::withMessages([
                "{$prefix}.weight_min" => 'Tỉ trọng min không được lớn hơn max.',
            ]);
        }
    }

    private function nullableWeight(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless($this->actor()->can($permission), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
