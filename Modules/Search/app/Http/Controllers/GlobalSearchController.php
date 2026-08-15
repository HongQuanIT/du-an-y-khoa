<?php

declare(strict_types=1);

namespace Modules\Search\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Search\Data\GlobalSearchQueryData;
use Modules\Search\Services\GlobalSearchService;
use Modules\Search\Support\SearchText;

final class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $search): View
    {
        $query = is_string($request->query('q')) ? SearchText::normalize((string) $request->query('q')) : '';
        $type = is_string($request->query('type')) ? SearchText::normalize((string) $request->query('type'), 40) : null;
        $type = $type !== '' ? $type : null;
        $page = max(1, (int) $request->query('page', 1));

        $result = $query !== ''
            ? $search->search(new GlobalSearchQueryData($query, $page, 20, $type))
            : null;

        if ($result !== null) {
            $result->paginator->appends($request->query());
        }

        return view('search::index', [
            'query' => $query,
            'type' => $type,
            'result' => $result,
            'suggestions' => $query === '' ? $search->suggest('', 6) : $search->suggest($query, 6),
        ]);
    }
}
