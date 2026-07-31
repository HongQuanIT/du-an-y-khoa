<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Student study-plan overview — "Lộ trình học" shell (srs/modules/04).
 *
 * Static port of html/pc-study-path.html until StudyPlan tasks land.
 */
final class StudyPlanPageController extends Controller
{
    public function __invoke(): View
    {
        return view('studyplan::index');
    }
}
