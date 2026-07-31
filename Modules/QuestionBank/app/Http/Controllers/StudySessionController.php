<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Study-mode question session shell (srs/modules/06).
 *
 * Static port of html/pc-study-session.html until live session state lands.
 */
final class StudySessionController extends Controller
{
    public function __invoke(): View
    {
        return view('questionbank::study-session', [
            'exitUrl' => route('qbank.create'),
        ]);
    }
}
