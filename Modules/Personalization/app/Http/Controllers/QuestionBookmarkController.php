<?php

declare(strict_types=1);

namespace Modules\Personalization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Personalization\Actions\SetQuestionBookmarkAction;
use Modules\QuestionBank\Models\Question;

final class QuestionBookmarkController extends Controller
{
    public function __invoke(
        Request $request,
        Question $question,
        SetQuestionBookmarkAction $setBookmark,
    ): JsonResponse {
        $validated = $request->validate([
            'bookmarked' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        assert($user !== null);

        $bookmarked = $setBookmark->handle(
            $user,
            $question,
            (bool) $validated['bookmarked'],
        );

        return ApiResponse::item(['bookmarked' => $bookmarked]);
    }
}
