<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Exam-mode session shell (srs/modules/01).
 *
 * Static port of html/pc-exam-session.html until timed exam state lands.
 */
final class ExamSessionController extends Controller
{
    public function __invoke(): View
    {
        return view('questionbank::exam-session');
    }
}
