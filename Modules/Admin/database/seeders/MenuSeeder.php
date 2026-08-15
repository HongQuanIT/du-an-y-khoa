<?php

declare(strict_types=1);

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Models\Menu;
use Modules\Admin\Support\Cms\MenuDefaults;
use Modules\Admin\Support\Enums\MenuKey;

final class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Menu::syncCatalog();

        foreach (MenuKey::cases() as $key) {
            $menu = Menu::findByKey($key);

            if ($menu === null) {
                continue;
            }

            $menu->update([
                'name' => $key->label(),
                'items' => MenuDefaults::for($key),
            ]);
        }
    }
}
