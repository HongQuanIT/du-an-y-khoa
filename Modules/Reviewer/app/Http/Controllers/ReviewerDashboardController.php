<?php

declare(strict_types=1);

namespace Modules\Reviewer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Reviewer\Actions\GetReviewerDashboardDataAction;

final class ReviewerDashboardController extends Controller
{
    public function __invoke(Request $request, GetReviewerDashboardDataAction $dashboard): View
    {
        return view('reviewer::dashboard', $dashboard->handle($request->user()));
    }
}
