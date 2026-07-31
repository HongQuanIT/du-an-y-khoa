<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Custom session builder shell (srs/modules/05).
 *
 * Static port of html/pc-custom-session.html until filter builder lands.
 */
final class CustomSessionController extends Controller
{
    public function __invoke(): View
    {
        return view('questionbank::custom-session');
    }
}
