<?php

declare(strict_types=1);

namespace Modules\Personalization\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Flashcards overview shell (srs/modules/18).
 *
 * Static port of html/pc-flashcards-dashboard.html until decks/SRS land.
 */
final class FlashcardsDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('personalization::flashcards.index');
    }
}
