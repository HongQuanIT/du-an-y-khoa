<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Student Q-Bank landing — session history shell (srs/modules/01).
 *
 * Static port of html/pc-question-bank.html until session rollups land.
 */
final class QuestionBankPageController extends Controller
{
    public function __invoke(): View
    {
        return view('questionbank::index');
    }
}
