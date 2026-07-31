<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Study-plan task session shell (srs/modules/04).
 *
 * Static port of html/pc-study-session.html; exits back to study-plan (not qbank).
 */
final class StudyPlanSessionController extends Controller
{
    public function __invoke(): View
    {
        return view('studyplan::session', [
            'exitUrl' => route('study-plan.detail'),
        ]);
    }
}
