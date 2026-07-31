<?php

declare(strict_types=1);

namespace Modules\Personalization\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Flashcard create shell (srs/modules/18).
 *
 * Static port of html/pc-flashcard-create.html until deck persistence lands.
 */
final class FlashcardCreateController extends Controller
{
    public function __invoke(): View
    {
        return view('personalization::flashcards.create');
    }
}
