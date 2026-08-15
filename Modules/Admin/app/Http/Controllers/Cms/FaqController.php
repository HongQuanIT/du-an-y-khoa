<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\Cms\DeleteFaqAction;
use Modules\Admin\Actions\Cms\ReorderFaqAction;
use Modules\Admin\Actions\Cms\SaveFaqAction;
use Modules\Admin\Http\Requests\Cms\SaveFaqRequest;
use Modules\Admin\Models\Faq;
use Modules\Admin\Support\Enums\FaqCategory;

final class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::CmsManage);

        $query = Faq::query()->ordered();

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', (string) $category);
        }

        if ($request->query('status') === 'published') {
            $query->where('is_published', true);
        } elseif ($request->query('status') === 'draft') {
            $query->where('is_published', false);
        }

        $faqs = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => Faq::query()->count(),
            'published' => Faq::query()->where('is_published', true)->count(),
            'draft' => Faq::query()->where('is_published', false)->count(),
        ];

        return view('admin::cms.faq.index', [
            'faqs' => $faqs,
            'categories' => FaqCategory::cases(),
            'stats' => $stats,
            'filters' => [
                'q' => $search,
                'category' => $request->query('category'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission(Permission::CmsManage);

        return view('admin::cms.faq.form', [
            'faq' => new Faq([
                'sort_order' => Faq::nextSortOrder(FaqCategory::TaiKhoan),
                'is_published' => false,
            ]),
            'categories' => FaqCategory::cases(),
        ]);
    }

    public function store(SaveFaqRequest $request, SaveFaqAction $save): RedirectResponse
    {
        $this->authorizePermission(Permission::CmsManage);

        $faq = $save->handle($this->actor(), $request);

        return redirect()
            ->route('admin.cms.faq.edit', $faq)
            ->with('status', $request->boolean('is_published')
                ? 'FAQ đã được xuất bản.'
                : 'FAQ đã được lưu nháp.');
    }

    public function edit(Faq $faq): View
    {
        $this->authorizePermission(Permission::CmsManage);

        return view('admin::cms.faq.form', [
            'faq' => $faq,
            'categories' => FaqCategory::cases(),
        ]);
    }

    public function update(SaveFaqRequest $request, Faq $faq, SaveFaqAction $save): RedirectResponse
    {
        $this->authorizePermission(Permission::CmsManage);

        $save->handle($this->actor(), $request, $faq);

        return redirect()
            ->route('admin.cms.faq.edit', $faq)
            ->with('status', $request->boolean('is_published')
                ? 'FAQ đã được cập nhật và xuất bản.'
                : 'FAQ đã được lưu nháp.');
    }

    public function destroy(Faq $faq, DeleteFaqAction $delete): RedirectResponse
    {
        $this->authorizePermission(Permission::CmsManage);

        $delete->handle($this->actor(), $faq);

        return redirect()
            ->route('admin.cms.faq.index')
            ->with('status', 'FAQ đã được xóa.');
    }

    public function moveUp(Faq $faq, ReorderFaqAction $reorder): RedirectResponse
    {
        $this->authorizePermission(Permission::CmsManage);

        $reorder->handle($faq, 'up');

        return back()->with('status', 'Đã đổi thứ tự FAQ.');
    }

    public function moveDown(Faq $faq, ReorderFaqAction $reorder): RedirectResponse
    {
        $this->authorizePermission(Permission::CmsManage);

        $reorder->handle($faq, 'down');

        return back()->with('status', 'Đã đổi thứ tự FAQ.');
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
