<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Study-plan detail / week timeline shell (srs/modules/04).
 *
 * Static port of html/pc-study-path-detail.html until plan persistence lands.
 */
final class StudyPlanDetailController extends Controller
{
    public function __invoke(): View
    {
        return view('studyplan::detail');
    }
}
