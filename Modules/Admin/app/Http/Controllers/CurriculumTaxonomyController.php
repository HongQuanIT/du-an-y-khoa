<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\PortalRoute;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Subject;

/**
 * Quản lý danh mục chương trình:
 *   Hệ cơ quan và Môn học độc lập với nhau.
 *   Bài học có thể gắn 0 hoặc nhiều môn học và 0 hoặc nhiều hệ cơ quan.
 *
 * Câu hỏi gắn vào bài học (question_lesson) — quản lý ở màn hình câu hỏi.
 * Trục nhãn triệu chứng / khái niệm sống song song ở module Tags.
 */
final class CurriculumTaxonomyController extends Controller
{
    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'active' => 'Đang dùng',
        'inactive' => 'Ngừng dùng',
    ];

    public const PER_PAGE = 20;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorizePermission('curriculum.view');

        $filters = $this->catalogFilters($request);
        $tab = $filters['tab'];
        $paginator = $this->paginateActiveTab($tab, $filters);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data' => $this->presentItems($tab, $paginator),
                'meta' => $this->presentMeta($paginator),
            ]);
        }

        return view('admin::curriculum.index', [
            'catalogItems' => $this->presentItems($tab, $paginator),
            'catalogMeta' => $this->presentMeta($paginator),
            'organSystems' => $tab === 'organ-systems' ? $paginator : $this->emptyPage(),
            'subjects' => $tab === 'subjects' ? $paginator : $this->emptyPage(),
            'lessons' => $tab === 'lessons' ? $paginator : $this->emptyPage(),
            'lessonSubjectOptions' => $tab === 'lessons'
                ? Subject::query()->orderBy('name')->get(['id', 'name', 'slug'])
                : collect(),
            'lessonOrganSystemOptions' => $tab === 'lessons'
                ? OrganSystem::query()->orderBy('name')->get(['id', 'name', 'slug'])
                : collect(),
            'subjectLessonOptions' => $tab === 'subjects'
                ? Lesson::query()->orderBy('name')->get(['id', 'name', 'slug', 'status'])
                : collect(),
            'filters' => $filters,
            'stats' => [
                'organ_systems' => OrganSystem::query()->count(),
                'subjects' => Subject::query()->count(),
                'lessons' => Lesson::query()->count(),
            ],
            'statuses' => TaxonomyStatus::cases(),
            'statusLabels' => self::STATUS_LABELS,
            'canCreate' => $this->actor()->can('curriculum.create'),
            'canUpdate' => $this->actor()->can('curriculum.update'),
            'canDelete' => $this->actor()->can('curriculum.delete'),
        ]);
    }

    // ------------------------------------------------------------------
    // Hệ cơ quan (Organ systems)
    // ------------------------------------------------------------------

    public function storeOrganSystem(Request $request): RedirectResponse
    {
        $this->authorizePermission('curriculum.create');

        $organSystem = OrganSystem::query()->create(
            $this->validatedAttributes($request, 'organ_systems'),
        );

        return $this->redirectToTab('organ-systems', $organSystem->id)
            ->with('status', 'Đã thêm hệ cơ quan «'.$organSystem->name.'».');
    }

    public function updateOrganSystem(Request $request, OrganSystem $organSystem): RedirectResponse
    {
        $this->authorizePermission('curriculum.update');

        $organSystem->update(
            $this->validatedAttributes($request, 'organ_systems', $organSystem->id),
        );

        return $this->redirectToTab('organ-systems', $organSystem->id)
            ->with('status', 'Đã cập nhật hệ cơ quan «'.$organSystem->name.'».');
    }

    public function destroyOrganSystem(OrganSystem $organSystem): RedirectResponse
    {
        $this->authorizePermission('curriculum.delete');

        $blocked = $this->lessonsBlockedBySoleLink($organSystem, 'organSystems');
        if ($blocked > 0) {
            return $this->redirectToTab('organ-systems', $organSystem->id)
                ->withErrors([
                    'delete' => 'Không thể xoá «'.$organSystem->name.'» vì có bài học được gắn với hệ cơ quan này. Hãy mở bài học đó, chọn thêm hệ cơ quan khác, rồi quay lại xoá.',
                ]);
        }

        $name = $organSystem->name;
        $organSystem->delete();

        return $this->redirectToTab('organ-systems')
            ->with('status', 'Đã xoá hệ cơ quan «'.$name.'».');
    }

    // ------------------------------------------------------------------
    // Môn học (Subjects)
    // ------------------------------------------------------------------

    public function storeSubject(Request $request): RedirectResponse
    {
        $this->authorizePermission('curriculum.create');

        $subject = Subject::query()->create(
            $this->validatedAttributes($request, 'subjects'),
        );

        return $this->redirectToTab('subjects', $subject->id)
            ->with('status', 'Đã thêm môn học «'.$subject->name.'».');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorizePermission('curriculum.update');

        $subject->update(
            $this->validatedAttributes($request, 'subjects', $subject->id),
        );

        return $this->redirectToTab('subjects', $subject->id)
            ->with('status', 'Đã cập nhật môn học «'.$subject->name.'».');
    }

    public function destroySubject(Subject $subject): RedirectResponse
    {
        $this->authorizePermission('curriculum.delete');

        $blocked = $this->lessonsBlockedBySoleLink($subject, 'subjects');
        if ($blocked > 0) {
            return $this->redirectToTab('subjects', $subject->id)
                ->withErrors([
                    'delete' => 'Không thể xoá «'.$subject->name.'» vì có bài học được gắn với môn học này. Hãy mở bài học đó, chọn thêm môn học khác, rồi quay lại xoá.',
                ]);
        }

        $name = $subject->name;
        $subject->delete();

        return $this->redirectToTab('subjects')
            ->with('status', 'Đã xoá môn học «'.$name.'».');
    }

    public function attachSubjectLessons(Request $request, Subject $subject): JsonResponse|RedirectResponse
    {
        $this->authorizePermission('curriculum.update');

        $subject->lessons()->syncWithoutDetaching($this->validatedAttachIds(
            $request,
            'lesson_ids',
            'lesson_id',
            'lessons,id',
        ));
        $subject->unsetRelation('lessons');

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'lessons' => $this->presentSubjectLessons($subject),
                'lessons_count' => $subject->lessons()->count(),
                'message' => 'Đã gắn bài học vào môn học.',
            ]);
        }

        return $this->redirectToTab('subjects', $subject->id)
            ->with('status', 'Đã gắn bài học vào môn học.');
    }

    public function detachSubjectLesson(Request $request, Subject $subject, Lesson $lesson): JsonResponse|RedirectResponse
    {
        $this->authorizePermission('curriculum.update');

        $subject->lessons()->detach($lesson->id);
        $subject->unsetRelation('lessons');

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'lessons' => $this->presentSubjectLessons($subject),
                'lessons_count' => $subject->lessons()->count(),
                'message' => 'Đã gỡ bài học khỏi môn học.',
            ]);
        }

        return $this->redirectToTab('subjects', $subject->id)
            ->with('status', 'Đã gỡ bài học khỏi môn học.');
    }

    // ------------------------------------------------------------------
    // Bài học (Lessons)
    // ------------------------------------------------------------------

    public function storeLesson(Request $request): RedirectResponse
    {
        $this->authorizePermission('curriculum.create');

        [$subjectIds, $organSystemIds] = $this->validatedLessonLinks($request);

        $lesson = Lesson::query()->create(
            $this->validatedAttributes($request, 'lessons'),
        );
        $lesson->subjects()->sync($subjectIds);
        $lesson->organSystems()->sync($organSystemIds);

        return $this->redirectToTab('lessons', $lesson->id)
            ->with('status', 'Đã thêm bài học «'.$lesson->name.'».');
    }

    public function updateLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->authorizePermission('curriculum.update');

        [$subjectIds, $organSystemIds] = $this->validatedLessonLinks($request);

        $lesson->update(
            $this->validatedAttributes($request, 'lessons', $lesson->id),
        );
        $lesson->subjects()->sync($subjectIds);
        $lesson->organSystems()->sync($organSystemIds);

        return $this->redirectToTab('lessons', $lesson->id)
            ->with('status', 'Đã cập nhật bài học «'.$lesson->name.'».');
    }

    public function destroyLesson(Lesson $lesson): RedirectResponse
    {
        $this->authorizePermission('curriculum.delete');

        $blocked = $lesson->questions()
            ->whereDoesntHave('lessons', fn ($query) => $query->whereKeyNot($lesson->getKey()))
            ->count();

        if ($blocked > 0) {
            return $this->redirectToTab('lessons', $lesson->id)
                ->withErrors([
                    'delete' => 'Không thể xoá «'.$lesson->name.'» vì có câu hỏi được gắn với bài học này. Hãy mở câu hỏi đó, chọn thêm bài học khác, rồi quay lại xoá.',
                ]);
        }

        $name = $lesson->name;
        $lesson->delete();

        return $this->redirectToTab('lessons')
            ->with('status', 'Đã xoá bài học «'.$name.'».');
    }

    public function attachLessonSubject(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->authorizePermission('curriculum.update');

        $lesson->subjects()->syncWithoutDetaching($this->validatedAttachIds(
            $request,
            'subject_ids',
            'subject_id',
            'subjects,id',
        ));

        return $this->redirectToTab('lessons', $lesson->id)
            ->with('status', 'Đã gắn bài học vào môn học.');
    }

    public function detachLessonSubject(Lesson $lesson, Subject $subject): RedirectResponse
    {
        $this->authorizePermission('curriculum.update');

        $lesson->subjects()->detach($subject->id);

        return $this->redirectToTab('lessons', $lesson->id)
            ->with('status', 'Đã gỡ liên kết môn học.');
    }

    public function attachLessonOrganSystem(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->authorizePermission('curriculum.update');

        $lesson->organSystems()->syncWithoutDetaching($this->validatedAttachIds(
            $request,
            'organ_system_ids',
            'organ_system_id',
            'organ_systems,id',
        ));

        return $this->redirectToTab('lessons', $lesson->id)
            ->with('status', 'Đã gắn bài học vào hệ cơ quan.');
    }

    public function detachLessonOrganSystem(Lesson $lesson, OrganSystem $organSystem): RedirectResponse
    {
        $this->authorizePermission('curriculum.update');

        $lesson->organSystems()->detach($organSystem->id);

        return $this->redirectToTab('lessons', $lesson->id)
            ->with('status', 'Đã gỡ liên kết hệ cơ quan.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Validate & normalise the shared taxonomy node fields for the given table.
     *
     * @return array<string, mixed>
     */
    private function validatedAttributes(Request $request, string $table, ?int $ignoreId = null): array
    {
        $name = trim((string) $request->input('name', ''));
        $slugInput = trim((string) $request->input('slug', ''));
        $slug = Str::slug($slugInput !== '' ? $slugInput : $name);
        if ($slug === '') {
            $slug = 'muc';
        }

        $request->merge([
            'name' => $name,
            'slug' => $slug,
        ]);

        $enforceUnique = in_array($table, ['organ_systems', 'subjects', 'lessons'], true);

        $data = $request->validate([
            'name' => array_values(array_filter([
                'required',
                'string',
                'max:255',
                $enforceUnique ? $this->uniqueCatalogName($table, $ignoreId) : null,
            ])),
            'slug' => array_values(array_filter([
                'required',
                'string',
                'max:191',
                $enforceUnique ? $this->uniqueCatalogSlug($table, $ignoreId) : null,
            ])),
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(TaxonomyStatus::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => filled($data['description'] ?? null) ? (string) $data['description'] : null,
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function uniqueCatalogName(string $table, ?int $ignoreId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($table, $ignoreId): void {
            $exists = DB::table($table)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $value))])
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                $fail('Tên này đã tồn tại.');
            }
        };
    }

    private function uniqueCatalogSlug(string $table, ?int $ignoreId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($table, $ignoreId): void {
            $slug = trim((string) $value);
            if ($slug === '') {
                return;
            }

            $exists = DB::table($table)
                ->whereRaw('LOWER(TRIM(slug)) = ?', [mb_strtolower($slug)])
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                $fail('Đường dẫn định danh này đã tồn tại.');
            }
        };
    }

    /**
     * Count lessons that would have no remaining link on this axis after delete.
     */
    private function lessonsBlockedBySoleLink(OrganSystem|Subject $node, string $relation): int
    {
        return $node->lessons()
            ->whereDoesntHave($relation, fn ($query) => $query->whereKeyNot($node->getKey()))
            ->count();
    }

    /**
     * @return array{0: list<int>, 1: list<int>}
     */
    private function validatedLessonLinks(Request $request): array
    {
        $data = $request->validate([
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'organ_system_ids' => ['nullable', 'array'],
            'organ_system_ids.*' => ['integer', 'exists:organ_systems,id'],
        ]);

        return [
            $this->uniqueIntIds($data['subject_ids'] ?? []),
            $this->uniqueIntIds($data['organ_system_ids'] ?? []),
        ];
    }

    /**
     * @return list<int>
     */
    private function validatedAttachIds(
        Request $request,
        string $arrayKey,
        string $singleKey,
        string $exists,
    ): array {
        $data = $request->validate([
            $arrayKey => ['required_without:'.$singleKey, 'array', 'min:1'],
            $arrayKey.'.*' => ['integer', 'exists:'.$exists],
            $singleKey => ['required_without:'.$arrayKey, 'integer', 'exists:'.$exists],
        ]);

        return $this->uniqueIntIds(array_filter([
            ...($data[$arrayKey] ?? []),
            $data[$singleKey] ?? null,
        ], fn ($id) => $id !== null));
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<int>
     */
    private function uniqueIntIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{tab: string, q: string, status: string, dir: string, subject_ids: list<int>, organ_system_ids: list<int>}
     */
    private function catalogFilters(Request $request): array
    {
        $tab = (string) $request->query('tab', 'organ-systems');
        if (! in_array($tab, ['organ-systems', 'subjects', 'lessons'], true)) {
            $tab = 'organ-systems';
        }

        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', ...TaxonomyStatus::values()], true)) {
            $status = 'all';
        }

        return [
            'tab' => $tab,
            'q' => trim((string) $request->query('q', '')),
            'status' => $status,
            'dir' => strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc',
            'subject_ids' => $this->queryIdList($request, 'subject_ids', 'subject_id'),
            'organ_system_ids' => $this->queryIdList($request, 'organ_system_ids', 'organ_system_id'),
        ];
    }

    /**
     * @return list<int>
     */
    private function queryIdList(Request $request, string $plural, string $singular): array
    {
        $raw = $request->query($plural, $request->query($singular));
        if (is_string($raw)) {
            $raw = preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (! is_array($raw)) {
            $raw = filled($raw) ? [$raw] : [];
        }

        return array_values(array_filter(
            $this->uniqueIntIds($raw),
            fn (int $id): bool => $id > 0,
        ));
    }

    /**
     * @param  array{q: string, status: string, dir: string, subject_ids: list<int>, organ_system_ids: list<int>}  $filters
     */
    private function paginateActiveTab(string $tab, array $filters): LengthAwarePaginator
    {
        return match ($tab) {
            'organ-systems' => $this->paginateCatalog(OrganSystem::query()->withCount('lessons'), $filters),
            'subjects' => $this->paginateCatalog(
                Subject::query()
                    ->withCount('lessons')
                    ->with(['lessons' => fn ($query) => $query->select('lessons.id', 'lessons.name', 'lessons.slug', 'lessons.status')]),
                $filters,
            ),
            default => $this->paginateLessons($filters),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentItems(string $tab, LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()
            ->map(fn ($item): array => $tab === 'lessons'
                ? $this->presentLesson($item)
                : $this->presentCatalogNode($item, $tab))
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
     * @return array<string, mixed>
     */
    private function presentCatalogNode(OrganSystem|Subject $item, string $tab): array
    {
        [$updateRoute, $destroyRoute] = $tab === 'subjects'
            ? [PortalRoute::content('curriculum.subjects.update'), PortalRoute::content('curriculum.subjects.destroy')]
            : [PortalRoute::content('curriculum.organ-systems.update'), PortalRoute::content('curriculum.organ-systems.destroy')];

        $payload = [
            'id' => $item->id,
            'name' => $item->name,
            'slug' => $item->slug,
            'description' => $item->description,
            'status' => $item->status->value,
            'lessons_count' => (int) $item->lessons_count,
            'update_url' => route($updateRoute, $item),
            'destroy_url' => route($destroyRoute, $item),
        ];

        if ($tab === 'subjects' && $item instanceof Subject) {
            $payload['lessons'] = $this->presentSubjectLessons($item);
            $payload['attach_lessons_url'] = route(PortalRoute::content('curriculum.subjects.lessons.attach'), $item);
            $payload['create_lesson_url'] = route(PortalRoute::content('curriculum.index'), [
                'tab' => 'lessons',
                'panel' => 'create',
                'subject_ids' => [$item->id],
            ]);
        }

        return $payload;
    }

    /**
     * @return list<array{id: int, name: string, slug: string, status: string, detach_url: string}>
     */
    private function presentSubjectLessons(Subject $subject): array
    {
        $subject->loadMissing(['lessons' => fn ($query) => $query
            ->select('lessons.id', 'lessons.name', 'lessons.slug', 'lessons.status')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.name')]);

        return $subject->lessons
            ->map(fn (Lesson $lesson): array => [
                'id' => (int) $lesson->id,
                'name' => $lesson->name,
                'slug' => $lesson->slug,
                'status' => $lesson->status->value,
                'detach_url' => route(PortalRoute::content('curriculum.subjects.lessons.detach'), [$subject, $lesson]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLesson(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'name' => $lesson->name,
            'slug' => $lesson->slug,
            'description' => $lesson->description,
            'status' => $lesson->status->value,
            'subject_ids' => $lesson->subjects->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'organ_system_ids' => $lesson->organSystems->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'subject_names' => $lesson->subjects->pluck('name')->values()->all(),
            'organ_system_names' => $lesson->organSystems->pluck('name')->values()->all(),
            'subjects_count' => (int) $lesson->subjects_count,
            'organ_systems_count' => (int) $lesson->organ_systems_count,
            'questions_count' => (int) $lesson->questions_count,
            'update_url' => route(PortalRoute::content('curriculum.lessons.update'), $lesson),
            'destroy_url' => route(PortalRoute::content('curriculum.lessons.destroy'), $lesson),
        ];
    }

    /**
     * @param  Builder<OrganSystem|Subject>  $query
     * @param  array{q: string, status: string, dir: string}  $filters
     * @return LengthAwarePaginator<int, OrganSystem|Subject>
     */
    private function paginateCatalog(Builder $query, array $filters): LengthAwarePaginator
    {
        $this->applyCatalogSearch($query, $filters);

        return $query
            ->orderBy('name', $filters['dir'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  array{q: string, status: string, dir: string, subject_ids: list<int>, organ_system_ids: list<int>}  $filters
     * @return LengthAwarePaginator<int, Lesson>
     */
    private function paginateLessons(array $filters): LengthAwarePaginator
    {
        $query = Lesson::query()
            ->with(['subjects:id,name,slug', 'organSystems:id,name,slug'])
            ->withCount(['subjects', 'organSystems', 'questions']);

        $this->applyCatalogSearch($query, $filters);

        if ($filters['subject_ids'] !== []) {
            $query->whereIn('id', DB::table('lesson_subject')
                ->whereIn('subject_id', $filters['subject_ids'])
                ->select('lesson_id'));
        }

        if ($filters['organ_system_ids'] !== []) {
            $query->whereIn('id', DB::table('lesson_organ_system')
                ->whereIn('organ_system_id', $filters['organ_system_ids'])
                ->select('lesson_id'));
        }

        return $query
            ->orderBy('name', $filters['dir'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  Builder<OrganSystem|Subject|Lesson>  $query
     * @param  array{q: string, status: string}  $filters
     */
    private function applyCatalogSearch(Builder $query, array $filters): void
    {
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $term = $filters['q'];
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)->orWhere('slug', 'like', $like);
        });
    }

    /**
     * @return LengthAwarePaginator<int, mixed>
     */
    private function emptyPage(): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, self::PER_PAGE);
    }

    private function redirectToTab(string $tab, ?int $focus = null): RedirectResponse
    {
        $page = null;
        if ($focus !== null) {
            $table = match ($tab) {
                'organ-systems' => 'organ_systems',
                'subjects' => 'subjects',
                default => 'lessons',
            };
            $name = DB::table($table)->where('id', $focus)->value('name');
            if (is_string($name) && $name !== '') {
                $page = intdiv((int) DB::table($table)->where('name', '<', $name)->count(), self::PER_PAGE) + 1;
            }
        }

        return redirect()->route(PortalRoute::content('curriculum.index'), array_filter([
            'tab' => $tab,
            'focus' => $focus,
            'page' => $page,
        ]));
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
