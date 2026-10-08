<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\PortalRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Auth\Models\Profession;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Support\BlueprintExamAllocator;
use Modules\QuestionBank\Support\ServePublishedQuestion;

final class ExamCatalogController extends Controller
{
    private const PER_PAGE = 20;

    /** @var array<string, string> */
    private const STATUS_LABELS = [
        'active' => 'Đang dùng',
        'inactive' => 'Ngừng dùng',
    ];

    public function index(Request $request, BlueprintExamAllocator $allocator): View|JsonResponse
    {
        $this->authorizePermission('blueprint.view');

        $filters = $this->filters($request);
        $paginator = $this->paginate($filters);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data' => $this->presentItems($paginator, $allocator),
                'meta' => $this->presentMeta($paginator),
            ]);
        }

        return view('admin::exam-catalogs.index', [
            'catalogItems' => $this->presentItems($paginator, $allocator),
            'catalogMeta' => $this->presentMeta($paginator),
            'filters' => $filters,
            'focusId' => $request->filled('focus') ? (int) $request->query('focus') : null,
            'openCreate' => $request->boolean('create'),
            'statuses' => TaxonomyStatus::cases(),
            'statusLabels' => self::STATUS_LABELS,
            'professions' => Profession::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'blueprints' => Blueprint::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'canCreate' => $this->actor()->can('blueprint.create'),
            'canUpdate' => $this->actor()->can('blueprint.update'),
            'canDelete' => $this->actor()->can('blueprint.delete'),
        ]);
    }

    public function create(): RedirectResponse
    {
        $this->authorizePermission('blueprint.create');

        return redirect()->route(PortalRoute::content('exam-catalogs.index'), ['create' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission('blueprint.create');

        [$attributes, $professionIds] = $this->validated($request);
        $catalog = ExamCatalog::query()->create($attributes);
        $catalog->professions()->sync($professionIds);

        return $this->redirectToIndex($catalog->id)
            ->with('status', 'Đã thêm kỳ thi «'.$catalog->name.'».');
    }

    public function edit(ExamCatalog $examCatalog): RedirectResponse
    {
        $this->authorizePermission('blueprint.update');

        return $this->redirectToIndex($examCatalog->id);
    }

    public function update(Request $request, ExamCatalog $examCatalog): RedirectResponse
    {
        $this->authorizePermission('blueprint.update');

        [$attributes, $professionIds] = $this->validated($request, $examCatalog);
        $examCatalog->update($attributes);
        $examCatalog->professions()->sync($professionIds);

        return $this->redirectToIndex($examCatalog->id)
            ->with('status', 'Đã cập nhật kỳ thi «'.$examCatalog->name.'».');
    }

    public function destroy(ExamCatalog $examCatalog): RedirectResponse
    {
        $this->authorizePermission('blueprint.delete');

        $name = $examCatalog->name;
        $examCatalog->delete();

        return $this->redirectToIndex()
            ->with('status', 'Đã xoá kỳ thi «'.$name.'».');
    }

    /**
     * @param  array{q: string, status: string, dir: string}  $filters
     */
    private function paginate(array $filters): LengthAwarePaginator
    {
        $q = $filters['q'];
        $like = '%'.addcslashes($q, '%_\\').'%';

        return ExamCatalog::query()
            ->with(['blueprint:id,name,slug,code,description,status,total_questions', 'professions:id,name'])
            ->withCount([
                'questions as attached_questions_count',
                'questions as bank_questions_count' => fn ($query) => ServePublishedQuestion::scopeAvailable($query),
            ])
            ->when($q !== '', function ($query) use ($like): void {
                $query->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('name', $filters['dir'])
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentItems(LengthAwarePaginator $paginator, BlueprintExamAllocator $allocator): array
    {
        return $paginator->getCollection()
            ->map(function (ExamCatalog $catalog) use ($allocator): array {
                $matrix = $catalog->blueprint !== null ? $allocator->allocate($catalog->blueprint) : null;

                return [
                    'id' => (int) $catalog->id,
                    'name' => $catalog->name,
                    'slug' => $catalog->slug,
                    'code' => $catalog->code,
                    'description' => $catalog->description,
                    'status' => $catalog->status->value,
                    'sort_order' => (int) $catalog->sort_order,
                    'blueprint_id' => $catalog->blueprint_id !== null ? (int) $catalog->blueprint_id : null,
                    'blueprint_name' => $catalog->blueprint?->name,
                    'profession_ids' => $catalog->professions->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                    'profession_names' => $catalog->professions->pluck('name')->values()->all(),
                    'questions_count' => ($matrix['ready'] ?? false) ? (int) $matrix['total_questions'] : 0,
                    'attached_questions_count' => (int) $catalog->attached_questions_count,
                    'bank_questions_count' => (int) $catalog->bank_questions_count,
                    'sample_exam_id' => $catalog->sample_exam_id,
                    'update_url' => route(PortalRoute::content('exam-catalogs.update'), $catalog),
                    'destroy_url' => route(PortalRoute::content('exam-catalogs.destroy'), $catalog),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{total: int, page: int, last_page: int, from: int|null, to: int|null}
     */
    private function presentMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * @return array{q: string, status: string, dir: string}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', ...TaxonomyStatus::values()], true)) {
            $status = 'all';
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => $status,
            'dir' => strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    private function validated(Request $request, ?ExamCatalog $catalog = null): array
    {
        $name = trim((string) $request->input('name', ''));
        $slug = $catalog?->slug;
        if (! is_string($slug) || $slug === '') {
            $generated = Str::slug($name);
            $slug = $this->uniqueSlug($generated !== '' ? $generated : 'ky-thi');
        }

        $code = trim((string) $request->input('code', ''));
        $request->merge([
            'name' => $name,
            'slug' => $slug,
            'code' => $code !== '' ? $code : null,
            'blueprint_id' => $request->filled('blueprint_id') ? (int) $request->input('blueprint_id') : null,
        ]);

        $uniqueCode = Rule::unique('exam_catalogs', 'code');
        $uniqueSlug = Rule::unique('exam_catalogs', 'slug');
        if ($catalog !== null) {
            $uniqueCode = $uniqueCode->ignore($catalog->id);
            $uniqueSlug = $uniqueSlug->ignore($catalog->id);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:191', $uniqueSlug],
            'code' => ['nullable', 'string', 'max:100', $uniqueCode],
            'description' => ['nullable', 'string', 'max:2000'],
            'blueprint_id' => ['nullable', 'integer', 'exists:blueprints,id'],
            'status' => ['required', Rule::in(TaxonomyStatus::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'profession_ids' => ['nullable', 'array'],
            'profession_ids.*' => ['integer', 'distinct', 'exists:professions,id'],
        ]);

        $professionIds = collect($data['profession_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return [[
            'name' => $data['name'],
            'slug' => $data['slug'],
            'code' => $data['code'] ?? null,
            'description' => filled($data['description'] ?? null) ? (string) $data['description'] : null,
            'blueprint_id' => $data['blueprint_id'] ?? null,
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? $catalog?->sort_order ?? 0),
        ], $professionIds];
    }

    private function uniqueSlug(string $base): string
    {
        $root = $base !== '' ? $base : 'ky-thi';
        $candidate = $root;
        $suffix = 1;

        while (ExamCatalog::query()->where('slug', $candidate)->exists()) {
            $candidate = $root.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function redirectToIndex(?int $focus = null): RedirectResponse
    {
        $params = [];
        if ($focus !== null) {
            $name = ExamCatalog::query()->whereKey($focus)->value('name');
            if (is_string($name) && $name !== '') {
                $before = ExamCatalog::query()->where('name', '<', $name)->count();
                $page = intdiv($before, self::PER_PAGE) + 1;
                $params['focus'] = $focus;
                if ($page > 1) {
                    $params['page'] = $page;
                }
            }
        }

        return redirect()->route(PortalRoute::content('exam-catalogs.index'), $params);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless($this->actor()->can($permission), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
