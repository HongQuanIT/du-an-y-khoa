<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Health endpoints for orchestrators/load balancers.
 * See srs/00-nen-tang/02-kien-truc-ky-thuat.md §7.
 */
final class HealthController extends Controller
{
    /** Liveness — process is up. */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /** Readiness — critical dependencies are reachable. */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(function (): bool {
                DB::connection()->getPdo();

                return true;
            }),
            'redis' => $this->check(function (): bool {
                Redis::connection()->ping();

                return true;
            }),
            'meilisearch' => $this->check($this->pingMeilisearch(...)),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ready' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            return (bool) $probe();
        } catch (Throwable) {
            return false;
        }
    }

    private function pingMeilisearch(): bool
    {
        $host = (string) config('scout.meilisearch.host');

        if ($host === '') {
            return false;
        }

        $response = @file_get_contents(rtrim($host, '/').'/health');

        return $response !== false && str_contains($response, 'available');
    }
}
