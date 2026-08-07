<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Models\Classroom;

final class LiveRecordingPlaybackController extends Controller
{
    public function __invoke(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
    ): RedirectResponse {
        $this->authorize('view', $classroom);
        abort_unless($classroom->isActiveMember($request->user()), 403);

        $recording = $liveSession->recordings()->latest()->first();
        abort_if($recording === null || blank($recording->playback_url), 404);

        return redirect()->away((string) $recording->playback_url);
    }
}
