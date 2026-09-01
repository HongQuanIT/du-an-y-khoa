<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;
use Modules\Admin\Actions\GetAdminDashboardDataAction;

final class DashboardController extends Controller
{
    public function __invoke(GetAdminDashboardDataAction $dashboard): View
    {
        $data = $dashboard->handle($this->actor());

        return view('admin::dashboard', $data);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
