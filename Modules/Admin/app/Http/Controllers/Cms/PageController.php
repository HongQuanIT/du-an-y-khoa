<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\Cms\SaveCmsPageAction;
use Modules\Admin\Http\Requests\Cms\SaveCmsPageRequest;
use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Enums\CmsPageKey;
use Modules\Admin\Support\Enums\CmsPageStatus;

final class PageController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::CmsManage);

        CmsPage::syncCatalog();

        $group = $request->query('group') === 'landing' ? 'landing' : 'static';

        $pages = CmsPage::query()
            ->get()
            ->keyBy(fn (CmsPage $page): string => $page->key->value);

        $keys = collect(CmsPageKey::cases())->filter(
            fn (CmsPageKey $key): bool => $group === 'landing'
                ? $key->isLandingBlock()
                : ! $key->isLandingBlock(),
        );

        $rows = $keys->map(function (CmsPageKey $key) use ($pages): array {
            $page = $pages->get($key->value);

            return [
                'key' => $key,
                'page' => $page,
            ];
        })->values();

        $groupPages = $rows
            ->pluck('page')
            ->filter();

        $stats = [
            'total' => $rows->count(),
            'published' => $groupPages->filter(fn (CmsPage $p) => $p->isPublished())->count(),
            'draft' => $groupPages->filter(fn (CmsPage $p) => ! $p->isPublished())->count(),
        ];

        return view('admin::cms.pages.index', [
            'rows' => $rows,
            'stats' => $stats,
            'group' => $group,
        ]);
    }

    public function edit(CmsPage $cmsPage): View
    {
        $this->authorizePermission(Permission::CmsManage);

        return view('admin::cms.pages.form', [
            'page' => $cmsPage,
            'content' => $cmsPage->resolvedContent(),
        ]);
    }

    public function update(SaveCmsPageRequest $request, CmsPage $cmsPage, SaveCmsPageAction $save): RedirectResponse
    {
        $this->authorizePermission(Permission::CmsManage);

        $save->handle($this->actor(), $request, $cmsPage);

        $isLanding = $cmsPage->key?->isLandingBlock() ?? false;

        $message = match ($request->input('action')) {
            'publish' => 'Trang đã được xuất bản.',
            'unpublish', 'draft' => $isLanding
                ? 'Đã ngừng xuất bản — trang public vẫn hiển thị nội dung mặc định.'
                : 'Trang đã ngừng xuất bản — URL công khai trả về 404.',
            default => $request->input('status') === CmsPageStatus::Published->value
                ? 'Đã lưu thay đổi (trang vẫn đang xuất bản).'
                : 'Đã lưu nháp.',
        };

        return redirect()
            ->route('admin.cms.pages.edit', $cmsPage)
            ->with('status', $message);
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
