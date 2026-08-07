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
     * @return array{token: string, url: string, role: string, configured: bool, room: string, identity: string}
     */
    public function issue(LiveSession $session, User $user, MemberRole $role): array
    {
        $room = $session->livekit_room_name ?? ('classroom-'.$session->uuid);
        $canPublish = $role->canPublish();
        $identity = 'user-'.$user->getKey();

        if (! $this->isConfigured()) {
            return [
                'token' => 'stub.'.$identity.'.'.$room,
                'url' => '',
                'role' => $canPublish ? 'publisher' : 'subscriber',
                'configured' => false,
                'room' => $room,
                'identity' => $identity,
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
                // Explicit sources so mic + screen share are allowed (not only camera).
                'canPublishSources' => $canPublish
                    ? ['camera', 'microphone', 'screen_share', 'screen_share_audio']
                    : [],
            ],
        ];

        return [
            'token' => $this->encodeJwt($payload, $apiSecret),
            'url' => $url,
            'role' => $canPublish ? 'publisher' : 'subscriber',
            'configured' => true,
            'room' => $room,
            'identity' => $identity,
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
