<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;

final class BlueprintController extends Controller
{
    public function index(): View
    {
        $this->authorizePermission(Permission::TopicView);

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
            'canCreate' => $this->actor()->can(Permission::TopicCreate->value),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission(Permission::TopicCreate);

        return view('admin::blueprints.form', $this->formData(new Blueprint([
            'status' => TaxonomyStatus::Active,
            'sort_order' => 0,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicCreate);
        $data = $this->validatedBlueprint($request);
        $blueprint = Blueprint::query()->create($data);

        return redirect()->route('admin.blueprints.edit', $blueprint)->with('status', 'Đã tạo ma trận đề thi.');
    }

    public function edit(Blueprint $blueprint): View
    {
        $this->authorizePermission(Permission::TopicView);
        $blueprint->load(['sections.coreClinicalTopics.medicalTaxonomyNodes']);

        return view('admin::blueprints.form', $this->formData($blueprint));
    }

    public function update(Request $request, Blueprint $blueprint): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);
        $blueprint->update($this->validatedBlueprint($request, $blueprint));

        return back()->with('status', 'Đã cập nhật ma trận đề thi.');
    }

    public function destroy(Blueprint $blueprint): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicDelete);
        $blueprint->update(['status' => TaxonomyStatus::Inactive]);

        return redirect()->route('admin.blueprints.index')->with('status', 'Đã vô hiệu hóa ma trận đề thi.');
    }

    public function storeSection(Request $request, Blueprint $blueprint): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicCreate);
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

        return back()->with('status', 'Đã thêm section.');
    }

    public function storeCoreTopic(Request $request, BlueprintSection $section): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicCreate);
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

        return back()->with('status', 'Đã thêm core clinical topic.');
    }

    public function syncCoreTopicMedicalNodes(Request $request, CoreClinicalTopic $topic): RedirectResponse|JsonResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);

        $data = $request->validate([
            'medical_taxonomy_node_ids' => ['nullable', 'array'],
            'medical_taxonomy_node_ids.*' => ['integer', 'exists:medical_taxonomy_nodes,id'],
        ]);

        $topic->medicalTaxonomyNodes()->sync($data['medical_taxonomy_node_ids'] ?? []);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Đã cập nhật mapping medical nodes cho core topic.',
                'data' => [
                    'medical_taxonomy_node_ids' => collect($data['medical_taxonomy_node_ids'] ?? [])
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all(),
                ],
            ]);
        }

        return back()->with('status', 'Đã cập nhật mapping medical nodes cho core topic.');
    }

    /** @return array<string, mixed> */
    private function formData(Blueprint $blueprint): array
    {
        return [
            'blueprint' => $blueprint,
            'statuses' => TaxonomyStatus::cases(),
            'canUpdate' => $blueprint->exists
                ? $this->actor()->can(Permission::TopicUpdate->value)
                : $this->actor()->can(Permission::TopicCreate->value),
            'canDelete' => $blueprint->exists && $this->actor()->can(Permission::TopicDelete->value),
        ];
    }

    /** @return array<string, mixed> */
    private function validatedBlueprint(Request $request, ?Blueprint $blueprint = null): array
    {
        $uniqueSlug = Rule::unique('blueprints', 'slug');
        if ($blueprint !== null) {
            $uniqueSlug = $uniqueSlug->ignore($blueprint->id);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191', $uniqueSlug],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(TaxonomyStatus::values())],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($data['name']);
        }

        return [
            'name' => $data['name'],
            'slug' => $slug,
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'sort_order' => (int) $data['sort_order'],
        ];
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

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
