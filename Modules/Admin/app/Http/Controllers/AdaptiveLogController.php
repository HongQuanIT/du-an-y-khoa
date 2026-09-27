<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\QuestionBank\Services\AdaptiveSessionBriefing;

/**
 * Temporary briefing of storage/logs/adaptive.log for non-technical readers.
 */
final class AdaptiveLogController extends Controller
{
    public function __invoke(AdaptiveSessionBriefing $briefing): View
    {
        $page = $briefing->page();

        return view('admin::adaptive-log', [
            'sessions' => $page['sessions'],
            'log_ready' => $briefing->logExists(),
        ]);
    }

    public function destroy(): RedirectResponse
    {
        $path = (string) config('logging.channels.adaptive.path');
        if ($path !== '' && is_file($path) && ! unlink($path)) {
            return redirect()
                ->route('admin.adaptive-briefing')
                ->withErrors(['log' => 'Không xóa được file log thích ứng.']);
        }

        return redirect()
            ->route('admin.adaptive-briefing')
            ->with('status', 'Đã xóa toàn bộ log thích ứng.');
    }
}
