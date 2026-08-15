<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\Cms\DeleteBannerAction;
use Modules\Admin\Actions\Cms\SaveBannerAction;
use Modules\Admin\Actions\Cms\ToggleBannerAction;
use Modules\Admin\Http\Requests\Cms\SaveBannerRequest;
use Modules\Admin\Models\Banner;
use Modules\Admin\Support\Enums\BannerAudience;
use Modules\Admin\Support\Enums\BannerPlacement;
use Modules\Admin\Support\Enums\BannerVariant;

final class BannerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::CmsManage);

        $query = Banner::query()->ordered();

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($placement = $request->query('placement')) {
            $query->where('placement', (string) $placement);
        }

        if ($request->query('status') === 'enabled') {
            $query->where('is_enabled', true);
        } elseif ($request->query('status') === 'disabled') {
            $query->where('is_enabled', false);
        }

        $banners = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => Banner::query()->count(),
            'enabled' => Banner::query()->where('is_enabled', true)->count(),
            'disabled' => Banner::query()->where('is_enabled', false)->count(),
        ];

        return view('admin::cms.banners.index', [
            'banners' => $banners,
            'stats' => $stats,
            'placements' => BannerPlacement::cases(),
            'filters' => [
                'q' => $search,
                'placement' => $request->query('placement'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission(Permission::CmsManage);

        return view('admin::cms.banners.form', [
            'banner' => new Banner([
                'variant' => BannerVariant::Info,
                'placement' => BannerPlacement::Both,
                'audience' => BannerAudience::All,
                'is_enabled' => false,
                'is_dismissible' => true,
                'sort_order' => Banner::nextSortOrder(),
            ]),
            'variants' => BannerVariant::cases(),
            'placements' => BannerPlacement::cases(),
            'audiences' => BannerAudience::cases(),
        ]);
    }

    public function store(SaveBannerRequest $request, SaveBannerAction $save): RedirectResponse
    {
        $this->authorizePermission(Permission::CmsManage);

        $banner = $save->handle($this->actor(), $request);

        return redirect()
            ->route('admin.cms.banners.edit', $banner)
            ->with('status', $banner->is_enabled
                ? 'Banner đã được bật.'
                : 'Banner đã được lưu (đang tắt).');
    }

    public function edit(Banner $banner): View
    {
        $this->authorizePermission(Permission::CmsManage);

        return view('admin::cms.banners.form', [
            'banner' => $banner,
            'variants' => BannerVariant::cases(),
            'placements' => BannerPlacement::cases(),
            'audiences' => BannerAudience::cases(),
        ]);
    }

    public function update(SaveBannerRequest $request, Banner $banner, SaveBannerAction $save): RedirectResponse
    {
        $this->authorizePermission(Permission::CmsManage);

        $save->handle($this->actor(), $request, $banner);

        return redirect()
            ->route('admin.cms.banners.edit', $banner)
            ->with('status', $request->boolean('is_enabled')
                ? 'Banner đã được cập nhật và bật.'
                : 'Banner đã được cập nhật (đang tắt).');
    }

    public function destroy(Banner $banner, DeleteBannerAction $delete): RedirectResponse
    {
        $this->authorizePermission(Permission::CmsManage);

        $delete->handle($this->actor(), $banner);

        return redirect()
            ->route('admin.cms.banners.index')
            ->with('status', 'Banner đã được xóa.');
    }

    public function toggle(Banner $banner, ToggleBannerAction $toggle): RedirectResponse
    {
        $this->authorizePermission(Permission::CmsManage);

        $banner = $toggle->handle($this->actor(), $banner);

        return back()->with('status', $banner->is_enabled
            ? 'Đã bật banner.'
            : 'Đã tắt banner.');
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
