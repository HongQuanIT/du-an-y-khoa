<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP helpers (secret, verify, otpauth QR as SVG data URI).
 */
final class TotpService
{
    public function __construct(
        private readonly Google2FA $google2fa = new Google2FA,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($code === '' || strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($secret, $code, 1);
    }

    public function qrDataUri(string $company, string $email, string $secret): string
    {
        $otpauth = $this->google2fa->getQRCodeUrl($company, $email, $secret);

        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(220),
                new SvgImageBackEnd,
            ),
        );

        $svg = $writer->writeString($otpauth);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
