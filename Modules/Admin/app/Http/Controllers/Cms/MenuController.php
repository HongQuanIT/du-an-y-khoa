<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\PortalRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Admin\Actions\Cms\SaveMenuAction;
use Modules\Admin\Http\Requests\Cms\SaveMenuRequest;
use Modules\Admin\Models\Menu;
use Modules\Admin\Support\Cms\MenuLinkRules;
use Modules\Admin\Support\Enums\MenuKey;

final class MenuController extends Controller
{
    public function index(): View
    {
        $this->authorizePermission('cms.view');

        Menu::syncCatalog();

        $menus = Menu::query()
            ->get()
            ->keyBy(fn (Menu $menu): string => $menu->key->value);

        $rows = collect(MenuKey::cases())->map(function (MenuKey $key) use ($menus): array {
            return [
                'key' => $key,
                'menu' => $menus->get($key->value),
            ];
        });

        return view('admin::cms.menus.index', [
            'rows' => $rows,
        ]);
    }

    public function edit(Menu $menu): View
    {
        $this->authorizePermission('cms.update');

        return view('admin::cms.menus.form', [
            'menu' => $menu,
            'items' => $menu->resolvedItems(),
            'routeOptions' => MenuLinkRules::routeOptions(),
        ]);
    }

    public function update(SaveMenuRequest $request, Menu $menu, SaveMenuAction $save): RedirectResponse
    {
        $this->authorizePermission('cms.update');

        $save->handle($this->actor(), $request, $menu);

        return redirect()
            ->route(PortalRoute::content('cms.menus.edit'), $menu)
            ->with('status', 'Menu đã được cập nhật.');
    }

    private function authorizePermission(string ...$permissions): void
    {
        abort_unless($this->actor()->canAny([...$permissions]), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
