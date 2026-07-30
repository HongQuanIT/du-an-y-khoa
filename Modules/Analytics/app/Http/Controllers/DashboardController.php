<?php

declare(strict_types=1);

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Student dashboard — the landing page after login (srs/modules/03).
 *
 * Static shell for now: the widgets show placeholder figures until Analytics
 * ships the daily rollups. Only the signed-in user is real.
 */
final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('analytics::dashboard');
    }
}
