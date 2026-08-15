<?php

declare(strict_types=1);

namespace Modules\Search\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Search\Services\GlobalSearchService;
use Modules\Search\Support\SearchText;

final class GlobalSearchSuggestController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $search): JsonResponse
    {
        $query = is_string($request->query('q')) ? SearchText::normalize((string) $request->query('q'), 100) : '';
        $limit = min(10, max(1, (int) $request->query('limit', 5)));

        return response()->json([
            'data' => $search->suggest($query, $limit),
        ]);
    }
}
