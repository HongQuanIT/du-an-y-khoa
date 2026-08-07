<?php

declare(strict_types=1);

namespace Modules\Classroom\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Classroom\Enums\RecordingStatus;
use Modules\Classroom\Events\LiveRecordingReady;
use Modules\Classroom\Models\LiveRecording;
use Modules\Classroom\Models\LiveSession;
use RuntimeException;

/** Starts/stops LiveKit Room Composite egress to S3-compatible storage. */
final class LiveKitEgressService
{
    public function isEnabled(): bool
    {
        return (bool) config('classroom.livekit.egress_enabled', false)
            && filled(config('classroom.livekit.api_key'))
            && filled(config('classroom.livekit.egress_bucket'));
    }

    public function startForSession(LiveSession $session): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $room = $session->livekit_room_name ?? ('classroom-'.$session->uuid);
        $pathPrefix = 'classroom-recordings/'.$session->uuid.'/';

        $payload = [
            'room_name' => $room,
            'layout' => 'speaker',
            'file_outputs' => [[
                'file_type' => 'MP4',
                'filepath' => $pathPrefix.'recording.mp4',
                's3' => $this->s3UploadConfig(),
            ]],
        ];

        $response = $this->post('StartRoomCompositeEgress', $payload);

        return isset($response['egress_id']) ? (string) $response['egress_id'] : null;
    }

    public function stop(string $egressId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->post('StopEgress', ['egress_id' => $egressId]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function handleWebhookEvent(array $event): void
    {
        $egressId = (string) ($event['egressInfo']['egressId'] ?? $event['egress_id'] ?? '');
        $status = (string) ($event['event'] ?? $event['status'] ?? '');

        if ($egressId === '') {
            return;
        }

        $recording = LiveRecording::query()->where('egress_id', $egressId)->first();

        if ($recording === null) {
            return;
        }

        if (str_contains($status, 'ended') || str_contains($status, 'EGRESS_COMPLETE')) {
            $fileResults = $event['egressInfo']['fileResults'] ?? $event['file_results'] ?? [];
            $location = is_array($fileResults) && isset($fileResults[0]['location'])
                ? (string) $fileResults[0]['location']
                : null;
            $duration = (int) ($event['egressInfo']['duration'] ?? $event['duration'] ?? 0);

            $recording->update([
                'status' => RecordingStatus::Ready,
                'playback_url' => $location,
                'hls_manifest' => $location,
                'duration_seconds' => $duration > 0 ? (int) round($duration / 1_000_000_000) : null,
            ]);

            $recording->load('session.classroom');
            event(new LiveRecordingReady($recording->session, $recording));
        } elseif (str_contains($status, 'failed') || str_contains($status, 'EGRESS_FAILED')) {
            $recording->update(['status' => RecordingStatus::Failed]);
        }
    }

    /** @return array<string, mixed> */
    private function s3UploadConfig(): array
    {
        return array_filter([
            'access_key' => config('classroom.livekit.egress_access_key'),
            'secret' => config('classroom.livekit.egress_secret_key'),
            'region' => config('classroom.livekit.egress_region'),
            'bucket' => config('classroom.livekit.egress_bucket'),
            'endpoint' => config('classroom.livekit.egress_endpoint') ?: null,
            'force_path_style' => filled(config('classroom.livekit.egress_endpoint')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $method, array $body): array
    {
        $baseUrl = $this->apiBaseUrl();
        $token = $this->serverToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($baseUrl.'/twirp/livekit.Egress/'.$method, $body);

        if (! $response->successful()) {
            Log::warning('LiveKit egress API failed', [
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('LiveKit egress request failed.');
        }

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    private function apiBaseUrl(): string
    {
        $wsUrl = (string) config('classroom.livekit.url');
        $httpUrl = preg_replace('#^wss?://#', 'https://', $wsUrl) ?: $wsUrl;

        return rtrim(str_replace('ws://', 'http://', $httpUrl), '/');
    }

    private function serverToken(): string
    {
        $apiKey = (string) config('classroom.livekit.api_key');
        $secret = (string) config('classroom.livekit.api_secret');
        $now = time();

        $payload = [
            'iss' => $apiKey,
            'sub' => 'egress-service',
            'nbf' => $now - 10,
            'exp' => $now + 600,
            'video' => [
                'roomRecord' => true,
                'roomAdmin' => true,
            ],
        ];

        return $this->encodeJwt($payload, $secret);
    }

    /** @param array<string, mixed> $payload */
    private function encodeJwt(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            rtrim(strtr(base64_encode(json_encode($header, JSON_THROW_ON_ERROR)), '+/', '-_'), '='),
            rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '='),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = rtrim(strtr(base64_encode($signature ?: ''), '+/', '-_'), '=');

        return implode('.', $segments);
    }
}
