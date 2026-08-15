<?php

declare(strict_types=1);

namespace Modules\Admin\Actions\Cms;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Admin\Http\Requests\Cms\SaveMenuRequest;
use Modules\Admin\Models\Menu;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\Cms\MenuLinkRules;
use Modules\Admin\Support\Cms\ResolvedMenu;
use Modules\Admin\Support\Enums\MenuKey;

final class SaveMenuAction
{
    use AsAction;

    public function handle(User $actor, SaveMenuRequest $request, Menu $menu): Menu
    {
        $key = $menu->key ?? MenuKey::Header;

        $before = [
            'name' => $menu->name,
            'items' => $menu->items,
        ];

        $items = MenuLinkRules::sanitize($key, (array) $request->validated('items'));

        $menu->fill([
            'name' => trim(strip_tags((string) $request->validated('name'))),
            'items' => $items,
        ]);
        $menu->save();

        ResolvedMenu::forget($key);

        Auditor::record(
            'cms.menu.update',
            $actor,
            $menu,
            $before,
            [
                'name' => $menu->name,
                'items' => $menu->items,
            ],
        );

        return $menu->refresh();
    }
}
