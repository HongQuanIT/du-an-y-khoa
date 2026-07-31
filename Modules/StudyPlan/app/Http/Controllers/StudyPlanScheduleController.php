<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Study-plan calendar / schedule shell (srs/modules/04).
 *
 * Static port of html/pc-study-schedule.html until calendar tasks land.
 */
final class StudyPlanScheduleController extends Controller
{
    public function __invoke(): View
    {
        return view('studyplan::schedule');
    }
}
