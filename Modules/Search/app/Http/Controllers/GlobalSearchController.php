<?php

declare(strict_types=1);

namespace Modules\Search\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Models\Classroom;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\Search\Data\GlobalSearchQueryData;
use Modules\Search\Services\GlobalSearchService;
use Modules\Search\Support\SearchText;

final class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $search): View|RedirectResponse
    {
        $query = is_string($request->query('q')) ? SearchText::normalize((string) $request->query('q')) : '';
        $type = is_string($request->query('type')) ? SearchText::normalize((string) $request->query('type'), 40) : null;
        $type = $type !== '' ? $type : null;
        $page = max(1, (int) $request->query('page', 1));

        if ($query !== '') {
            $exactExam = Exam::query()
                ->where('status', ExamStatus::Published)
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($query)])
                ->first();

            if ($exactExam !== null) {
                return redirect()->route('exam.index');
            }

            $exactClassroom = Classroom::query()
                ->where('status', ClassroomStatus::Active)
                ->where('visibility', ClassroomVisibility::Public)
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($query)])
                ->first();

            if ($exactClassroom !== null) {
                return redirect()->route('classroom.show', $exactClassroom);
            }

            if ($request->user() !== null) {
                DB::table('search_histories')->insert([
                    'user_id' => $request->user()->getAuthIdentifier(),
                    'query' => $query,
                    'scope' => 'global',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

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
