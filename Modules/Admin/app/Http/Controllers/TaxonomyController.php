<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\View\View;
use Modules\Admin\Actions\BuildTaxonomyOverviewAction;

final class TaxonomyController extends Controller
{
    public function index(BuildTaxonomyOverviewAction $overview): View
    {
        $this->authorizePermission('taxonomy.view');

        return view('admin::taxonomy.index', $overview->handle($this->actor()));
    }

    private function authorizePermission(string|Permission ...$permissions): void
    {
        $names = array_map(
            static fn (string|Permission $permission): string => $permission instanceof Permission ? $permission->value : $permission,
            $permissions,
        );

        abort_unless($this->actor()->canAny($names), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
