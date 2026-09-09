<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
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
 * Quản lý danh mục chương trình 3 cấp (DAG):
 *   Hệ cơ quan (OrganSystem) → Môn học (Subject) → Bài học (Lesson).
 *
 * - Một môn học thuộc nhiều hệ cơ quan (subject_organ_system).
 * - Một bài học thuộc nhiều môn học (lesson_subject).
 * - Câu hỏi gắn vào bài học (question_lesson) — quản lý ở màn hình câu hỏi.
 *
 * Trục nhãn triệu chứng / khái niệm sống song song ở module Tags.
 */
final class CurriculumTaxonomyController extends Controller
{
    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'active' => 'Đang dùng',
        'inactive' => 'Ngừng dùng',
    ];

    public function index(): View
    {
        $this->authorizePermission(Permission::TopicView);

        $organSystems = OrganSystem::query()
            ->with(['subjects:id,name,slug'])
            ->withCount('subjects')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $subjects = Subject::query()
            ->with(['organSystems:id,name,slug', 'lessons:id,name,slug'])
            ->withCount(['organSystems', 'lessons'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $lessons = Lesson::query()
            ->with(['subjects:id,name,slug'])
            ->withCount(['subjects', 'questions'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin::curriculum.index', [
            'organSystems' => $organSystems,
            'subjects' => $subjects,
            'lessons' => $lessons,
            'stats' => [
                'organ_systems' => $organSystems->count(),
                'subjects' => $subjects->count(),
                'lessons' => $lessons->count(),
            ],
            'statuses' => TaxonomyStatus::cases(),
            'statusLabels' => self::STATUS_LABELS,
            'canCreate' => $this->actor()->can(Permission::TopicCreate->value),
            'canUpdate' => $this->actor()->can(Permission::TopicUpdate->value),
        ]);
    }

    // ------------------------------------------------------------------
    // Hệ cơ quan (Organ systems)
    // ------------------------------------------------------------------

    public function storeOrganSystem(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicCreate);

        $organSystem = OrganSystem::query()->create(
            $this->validatedAttributes($request, 'organ_systems'),
        );

        return $this->redirectToTab('organ-systems', $organSystem->id)
            ->with('status', 'Đã thêm hệ cơ quan «'.$organSystem->name.'».');
    }

    public function updateOrganSystem(Request $request, OrganSystem $organSystem): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);

        $organSystem->update(
            $this->validatedAttributes($request, 'organ_systems', $organSystem->id),
        );

        return $this->redirectToTab('organ-systems', $organSystem->id)
            ->with('status', 'Đã cập nhật hệ cơ quan «'.$organSystem->name.'».');
    }

    // ------------------------------------------------------------------
    // Môn học (Subjects)
    // ------------------------------------------------------------------

    public function storeSubject(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicCreate);

        $subject = Subject::query()->create(
            $this->validatedAttributes($request, 'subjects'),
        );
        $subject->organSystems()->sync($this->validatedOrganSystemIds($request));

        return $this->redirectToTab('subjects', $subject->id)
            ->with('status', 'Đã thêm môn học «'.$subject->name.'».');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);

        $subject->update(
            $this->validatedAttributes($request, 'subjects', $subject->id),
        );
        $subject->organSystems()->sync($this->validatedOrganSystemIds($request));

        return $this->redirectToTab('subjects', $subject->id)
            ->with('status', 'Đã cập nhật môn học «'.$subject->name.'».');
    }

    public function attachSubjectOrganSystem(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);

        $data = $request->validate([
            'organ_system_ids' => ['required_without:organ_system_id', 'array', 'min:1'],
            'organ_system_ids.*' => ['integer', 'exists:organ_systems,id'],
            'organ_system_id' => ['required_without:organ_system_ids', 'integer', 'exists:organ_systems,id'],
        ]);

        $ids = collect($data['organ_system_ids'] ?? [])
            ->push($data['organ_system_id'] ?? null)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $subject->organSystems()->syncWithoutDetaching($ids);

        return $this->redirectToTab('subjects', $subject->id)
            ->with('status', 'Đã gắn môn học vào hệ cơ quan.');
    }

    public function detachSubjectOrganSystem(Subject $subject, OrganSystem $organSystem): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);

        $subject->organSystems()->detach($organSystem->id);

        return $this->redirectToTab('subjects', $subject->id)
            ->with('status', 'Đã gỡ liên kết hệ cơ quan.');
    }

    // ------------------------------------------------------------------
    // Bài học (Lessons)
    // ------------------------------------------------------------------

    public function storeLesson(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicCreate);

        $lesson = Lesson::query()->create(
            $this->validatedAttributes($request, 'lessons'),
        );
        $lesson->subjects()->sync($this->validatedSubjectIds($request));

        return $this->redirectToTab('lessons', $lesson->id)
            ->with('status', 'Đã thêm bài học «'.$lesson->name.'».');
    }

    public function updateLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);

        $lesson->update(
            $this->validatedAttributes($request, 'lessons', $lesson->id),
        );
        $lesson->subjects()->sync($this->validatedSubjectIds($request));

        return $this->redirectToTab('lessons', $lesson->id)
            ->with('status', 'Đã cập nhật bài học «'.$lesson->name.'».');
    }

    public function attachLessonSubject(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);

        $data = $request->validate([
            'subject_ids' => ['required_without:subject_id', 'array', 'min:1'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'subject_id' => ['required_without:subject_ids', 'integer', 'exists:subjects,id'],
        ]);

        $ids = collect($data['subject_ids'] ?? [])
            ->push($data['subject_id'] ?? null)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $lesson->subjects()->syncWithoutDetaching($ids);

        return $this->redirectToTab('lessons', $lesson->id)
            ->with('status', 'Đã gắn bài học vào môn học.');
    }

    public function detachLessonSubject(Lesson $lesson, Subject $subject): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);

        $lesson->subjects()->detach($subject->id);

        return $this->redirectToTab('lessons', $lesson->id)
            ->with('status', 'Đã gỡ liên kết môn học.');
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(TaxonomyStatus::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($data['name']);
        }

        return [
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($table, $slug, $ignoreId),
            'code' => filled($data['code'] ?? null) ? (string) $data['code'] : null,
            'description' => filled($data['description'] ?? null) ? (string) $data['description'] : null,
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /** @return list<int> */
    private function validatedOrganSystemIds(Request $request): array
    {
        $data = $request->validate([
            'organ_system_ids' => ['nullable', 'array'],
            'organ_system_ids.*' => ['integer', 'exists:organ_systems,id'],
        ]);

        return collect($data['organ_system_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function validatedSubjectIds(Request $request): array
    {
        $data = $request->validate([
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        return collect($data['subject_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function uniqueSlug(string $table, string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug !== '' ? $slug : 'muc');
        if ($base === '') {
            $base = 'muc';
        }

        $candidate = $base;
        $suffix = 1;

        while (DB::table($table)
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function redirectToTab(string $tab, ?int $focus = null): RedirectResponse
    {
        return redirect()->route('admin.curriculum.index', array_filter([
            'tab' => $tab,
            'focus' => $focus,
        ]));
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
