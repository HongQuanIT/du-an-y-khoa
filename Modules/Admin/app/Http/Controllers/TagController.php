<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Tag;

final class TagController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission('tag.view');

        $query = Tag::query()->withCount('questions');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return view('admin::tags.index', [
            'tags' => $query->orderBy('name')->paginate(30)->withQueryString(),
            'filters' => ['q' => $search],
            'canCreate' => $this->actor()->canAny(['tag.create']),
            'canUpdate' => $this->actor()->canAny(['tag.update']),
            'canDelete' => $this->actor()->canAny(['tag.delete']),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('tag.create');

        return view('admin::tags.form', $this->formData(new Tag(['status' => TaxonomyStatus::Active])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission('tag.create');
        $tag = Tag::query()->create($this->validated($request));

        return redirect()->route('admin.tags.edit', $tag)->with('status', 'Đã tạo tag.');
    }

    public function edit(Tag $tag): View
    {
        $this->authorizePermission('tag.update');

        return view('admin::tags.form', $this->formData($tag));
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorizePermission('tag.update');
        $tag->update($this->validated($request, $tag));

        return back()->with('status', 'Đã cập nhật tag.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorizePermission('tag.delete');

        if ($tag->questions()->exists()) {
            $tag->update(['status' => TaxonomyStatus::Inactive]);

            return back()->with('status', 'Tag đang được dùng — đã chuyển sang inactive.');
        }

        $tag->delete();

        return redirect()->route('admin.tags.index')->with('status', 'Đã xóa tag.');
    }

    /** @return array<string, mixed> */
    private function formData(Tag $tag): array
    {
        return [
            'tag' => $tag,
            'statuses' => TaxonomyStatus::cases(),
            'canUpdate' => $tag->exists
                ? $this->actor()->canAny(['tag.update'])
                : $this->actor()->canAny(['tag.create']),
            'canDelete' => $tag->exists && $this->actor()->canAny(['tag.delete']),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Tag $tag = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191'],
            'type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(TaxonomyStatus::values())],
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($data['name']);
        }

        $slugRule = Rule::unique('tags', 'slug');
        if ($tag !== null) {
            $slugRule = $slugRule->ignore($tag->id);
        }

        $request->validate(['slug' => [$slugRule]]);

        return [
            'name' => $data['name'],
            'slug' => $slug,
            'type' => $data['type'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ];
    }

    private function authorizePermission(string|Permission ...$permissions): void
    {
        $names = array_map(
            static fn (string|Permission $permission): string => $permission instanceof Permission ? $permission->value : $permission,
            $permissions,
        );

        abort_unless($this->actor()->canAny($names), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
