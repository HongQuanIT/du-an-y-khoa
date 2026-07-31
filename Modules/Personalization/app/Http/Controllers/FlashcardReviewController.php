<?php

declare(strict_types=1);

namespace Modules\Personalization\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Flashcard review session shell (srs/modules/18).
 *
 * Static port of html/pc-flashcard-review.html until SRS ratings persist.
 */
final class FlashcardReviewController extends Controller
{
    public function __invoke(): View
    {
        return view('personalization::flashcards.review');
    }
}
