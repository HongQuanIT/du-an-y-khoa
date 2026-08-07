<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Services\LiveKitEgressService;

final class LiveKitWebhookController extends Controller
{
    public function __invoke(Request $request, LiveKitEgressService $egress): JsonResponse
    {
        $secret = (string) config('classroom.livekit.webhook_secret');

        if ($secret !== '' && $request->header('Authorization') !== 'Bearer '.$secret) {
            abort(401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $egress->handleWebhookEvent($payload);

        return response()->json(['ok' => true]);
    }
}
