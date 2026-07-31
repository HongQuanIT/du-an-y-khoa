<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Study-plan create wizard shell (srs/modules/04).
 *
 * Static port of html/pc-study-path-create.html until wizard persistence lands.
 */
final class StudyPlanCreateController extends Controller
{
    public function __invoke(): View
    {
        return view('studyplan::create');
    }
}
