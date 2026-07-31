<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Question review shell after a finished session (srs/modules/01).
 *
 * Static port of html/pc-question-review.html until review state persists.
 */
final class QuestionReviewController extends Controller
{
    public function __invoke(): View
    {
        return view('questionbank::review');
    }
}
