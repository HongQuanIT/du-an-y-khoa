<?php

declare(strict_types=1);

namespace Modules\Classroom\Services;

use App\Models\User;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Models\LiveSession;
use RuntimeException;

/**
 * Issues LiveKit Access Tokens (HS256 JWT).
 *
 * @see https://docs.livekit.io/home/server/generating-tokens/
 */
final class LiveKitTokenService
{
    public function isConfigured(): bool
    {
        $cfg = config('classroom.livekit', []);

        return filled($cfg['url'] ?? null)
            && filled($cfg['api_key'] ?? null)
            && filled($cfg['api_secret'] ?? null);
    }

    /**
     * @param  list<string>|null  $publishSources
     * @return array{token: string, url: string, role: string, configured: bool, room: string, identity: string, can_publish_audio: bool, can_publish_video: bool, can_publish_screen: bool}
     */
    public function issue(LiveSession $session, User $user, MemberRole $role, ?array $publishSources = null): array
    {
        $room = $session->livekit_room_name ?? ('classroom-'.$session->uuid);
        $sources = $publishSources ?? ($role->canPublish()
            ? ['camera', 'microphone', 'screen_share', 'screen_share_audio']
            : []);
        $sources = array_values(array_unique($sources));
        $canPublish = $sources !== [];
        $canPublishAudio = in_array('microphone', $sources, true);
        $canPublishVideo = in_array('camera', $sources, true);
        $canPublishScreen = in_array('screen_share', $sources, true);
        $identity = 'user-'.$user->getKey();
        $clientRole = $role->canPublish()
            ? 'publisher'
            : ($canPublish ? 'speaker' : 'subscriber');

        if (! $this->isConfigured()) {
            return [
                'token' => 'stub.'.$identity.'.'.$room,
                'url' => '',
                'role' => $clientRole,
                'configured' => false,
                'room' => $room,
                'identity' => $identity,
                'can_publish_audio' => $canPublishAudio,
                'can_publish_video' => $canPublishVideo,
                'can_publish_screen' => $canPublishScreen,
            ];
        }

        $url = (string) config('classroom.livekit.url');
        $apiKey = (string) config('classroom.livekit.api_key');
        $apiSecret = (string) config('classroom.livekit.api_secret');
        $ttl = max(60, (int) config('classroom.livekit.token_ttl_seconds', 3600));

        $now = time();
        $payload = [
            'iss' => $apiKey,
            'sub' => $identity,
            'nbf' => $now - 10,
            'exp' => $now + $ttl,
            'name' => $user->name,
            'video' => [
                'roomJoin' => true,
                'room' => $room,
                'canPublish' => $canPublish,
                'canSubscribe' => true,
                'canPublishData' => true,
                'canPublishSources' => $sources,
            ],
        ];

        return [
            'token' => $this->encodeJwt($payload, $apiSecret),
            'url' => $url,
            'role' => $clientRole,
            'configured' => true,
            'room' => $room,
            'identity' => $identity,
            'can_publish_audio' => $canPublishAudio,
            'can_publish_video' => $canPublishVideo,
            'can_publish_screen' => $canPublishScreen,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeJwt(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);

        if ($signature === false) {
            throw new RuntimeException('Unable to sign LiveKit JWT.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
