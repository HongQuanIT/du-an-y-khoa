<?php

declare(strict_types=1);

namespace Modules\Personalization\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Flashcard deck detail shell (srs/modules/18).
 *
 * Static port of html/pc-flashcard-deck-detail.html until deck CRUD lands.
 */
final class FlashcardDeckDetailController extends Controller
{
    public function __invoke(): View
    {
        return view('personalization::flashcards.deck');
    }
}
