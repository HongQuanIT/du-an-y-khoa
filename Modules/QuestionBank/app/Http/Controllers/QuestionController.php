<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\QuestionBank\Actions\ListQuestionsAction;
use Modules\QuestionBank\Http\Requests\ListQuestionsRequest;
use Modules\QuestionBank\Http\Resources\QuestionResource;
use Modules\QuestionBank\Models\Question;

/**
 * Thin controller: validate → delegate to action → present.
 */
final class QuestionController extends Controller
{
    public function index(ListQuestionsRequest $request, ListQuestionsAction $action): JsonResponse
    {
        $paginator = $action->handle($request->toData());

        return ApiResponse::paginated(
            $paginator,
            QuestionResource::collection($paginator->items()),
        );
    }

    public function show(Question $question): JsonResponse
    {
        $this->authorize('view', $question);

        return ApiResponse::item(new QuestionResource($question));
    }
}
