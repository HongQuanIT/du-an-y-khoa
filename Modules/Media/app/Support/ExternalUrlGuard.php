<?php

declare(strict_types=1);

namespace Modules\Media\Support;

use InvalidArgumentException;

final class ExternalUrlGuard
{
    /**
     * @throws InvalidArgumentException
     */
    public static function assertHttpUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || mb_strlen($url) > 2048) {
            throw new InvalidArgumentException('URL không hợp lệ.');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            throw new InvalidArgumentException('Chỉ chấp nhận URL http hoặc https.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('URL không hợp lệ.');
        }

        return $url;
    }

    /**
     * Block localhost / private / metadata IPs before the server fetches the URL.
     *
     * @throws InvalidArgumentException
     */
    public static function assertSafeToFetch(string $url): void
    {
        $url = self::assertHttpUrl($url);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException('Không được tải từ địa chỉ nội bộ.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            throw new InvalidArgumentException('Không phân giải được host của URL.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Không được tải từ địa chỉ nội bộ.');
            }
        }
    }
}
