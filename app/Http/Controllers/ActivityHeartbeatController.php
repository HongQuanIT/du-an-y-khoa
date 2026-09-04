<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit\ActivityTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ActivityHeartbeatController extends Controller
{
    public function __invoke(Request $request, ActivityTracker $tracker): JsonResponse
    {
        abort_unless(config('audit.activity.enabled', true), 404);

        $validated = $request->validate([
            'event' => ['required', 'in:start,leave'],
            'session_id' => ['required', 'uuid'],
            'area' => ['required', 'string', 'max:180', 'regex:/^\/[A-Za-z0-9_\-\/.%]*$/'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($validated['event'] === 'start') {
            $tracker->start($user, $validated['session_id'], $validated['area'], $request);
        } else {
            $tracker->leave($user, $validated['session_id'], $validated['area'], $request);
        }

        return response()->json(['ok' => true]);
    }
}
