<?php

declare(strict_types=1);

namespace App\Support\Queue\Concerns;

/**
 * Helper gom tag Horizon theo prefix tính năng (vd. billing, admin-reports).
 */
trait HasQueueDisplayName
{
    /** @return list<string> */
    protected function featureTags(string $feature, string ...$extra): array
    {
        $tags = [$feature];

        foreach ($extra as $tag) {
            if ($tag !== '') {
                $tags[] = $feature.':'.$tag;
            }
        }

        return $tags;
    }
}
