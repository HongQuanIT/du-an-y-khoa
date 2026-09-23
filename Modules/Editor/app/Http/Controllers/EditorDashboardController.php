<?php

declare(strict_types=1);

namespace Modules\Editor\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;
use Modules\Editor\Actions\GetEditorDashboardDataAction;

final class EditorDashboardController extends Controller
{
    public function __invoke(GetEditorDashboardDataAction $dashboard): View
    {
        /** @var User $editor */
        $editor = auth()->user();

        return view('editor::dashboard', $dashboard->handle($editor));
    }
}
