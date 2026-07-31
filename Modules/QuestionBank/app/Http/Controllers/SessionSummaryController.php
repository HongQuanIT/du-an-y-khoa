<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Session summary / statistics shell (srs/modules/01).
 *
 * Static port of html/pc-statistics.html until session analytics land.
 */
final class SessionSummaryController extends Controller
{
    public function __invoke(): View
    {
        return view('questionbank::summary');
    }
}
