<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\PortalAccess;
use App\Support\Enums\Permission;
use App\Support\Enums\PortalGroup;
use Illuminate\View\View;
use Modules\Classroom\Actions\GetTeachDashboardDataAction;

final class TeachDashboardController extends Controller
{
    public function __invoke(GetTeachDashboardDataAction $dashboard): View
    {
        $actor = $this->actor();

        abort_unless(
            PortalAccess::allows($actor, PortalGroup::Instructor)
            && (
                $actor->can('teaching_dashboard.view')
                || $actor->can(Permission::ClassroomManage->value)
                || $actor->can(Permission::QuestionReview->value)
            ),
            403,
        );

        return view('classroom::teach.dashboard', $dashboard->handle($actor));
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
