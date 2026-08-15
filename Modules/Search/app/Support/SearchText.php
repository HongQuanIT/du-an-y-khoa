<?php

declare(strict_types=1);

namespace Modules\Search\Support;

use Illuminate\Support\Str;

final class SearchText
{
    public static function normalize(string $value, int $maxLength = 255): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $value) ?? '';

        return mb_substr(Str::squish($value), 0, $maxLength);
    }

    public static function plain(string $html): string
    {
        return Str::squish(strip_tags(html_entity_decode(
            $html,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        )));
    }

    /** Return escaped HTML containing only generated <mark> elements. */
    public static function highlight(string $text, string $query, int $maxLength = 240): string
    {
        $plain = self::plain($text);
        $needle = self::plain($query);
        $position = $needle === '' ? false : mb_stripos($plain, $needle);
        $start = $position === false ? 0 : max(0, $position - 70);
        $snippet = mb_substr($plain, $start, $maxLength);

        if ($start > 0) {
            $snippet = '…'.$snippet;
        }
        if ($start + $maxLength < mb_strlen($plain)) {
            $snippet .= '…';
        }

        $escaped = htmlspecialchars($snippet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedNeedle = htmlspecialchars($needle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($escapedNeedle === '') {
            return $escaped;
        }

        return preg_replace(
            '/'.preg_quote($escapedNeedle, '/').'/iu',
            '<mark>$0</mark>',
            $escaped,
            1,
        ) ?? $escaped;
    }

    /** Escape SQL LIKE metacharacters with a portable explicit escape char. */
    public static function likePattern(string $query): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%';
    }
}
