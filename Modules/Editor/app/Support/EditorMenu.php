<?php

declare(strict_types=1);

namespace Modules\Editor\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class EditorMenu
{
    /**
     * @return list<array{label: string, icon: string, route: string, match: string}>
     */
    public static function for(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $items = [
            ['label' => 'Tổng quan', 'icon' => 'dashboard', 'route' => 'editor.dashboard', 'match' => 'editor.dashboard', 'permissions' => ['editor_dashboard.view']],
            ['label' => 'Câu hỏi', 'icon' => 'quiz', 'route' => 'editor.questions.index', 'match' => 'editor.questions.*', 'permissions' => ['editor_question.view']],
            ['label' => 'Phân loại', 'icon' => 'category', 'route' => 'editor.taxonomy.index', 'match' => 'editor.taxonomy.*', 'permissions' => ['editor_taxonomy.view']],
            ['label' => 'CMS', 'icon' => 'article', 'route' => 'editor.cms.pages.index', 'match' => 'editor.cms.*', 'permissions' => ['cms.view']],
            ['label' => 'Media', 'icon' => 'perm_media', 'route' => 'editor.media.index', 'match' => 'editor.media.*', 'permissions' => ['editor_media.view']],
        ];

        return array_values(array_filter($items, static function (array $item) use ($user): bool {
            return Route::has($item['route']) && $user->canAny($item['permissions']);
        }));
    }
}
