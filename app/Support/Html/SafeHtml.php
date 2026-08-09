<?php

declare(strict_types=1);

namespace App\Support\Html;

/**
 * Sanitize rich-text from the admin editor and prepare safe HTML for display.
 */
final class SafeHtml
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><blockquote><a><img><sub><sup><span>';

    public static function fromEditor(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $html = trim($html);

        if ($html === '' || $html === '<p><br></p>' || $html === '<p></p>') {
            return '';
        }

        return self::sanitize($html);
    }

    /**
     * Render stored content: plain text stays escaped; HTML is re-sanitized.
     */
    public static function forDisplay(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! self::looksLikeHtml($value)) {
            return nl2br(e($value), false);
        }

        return self::sanitize($value);
    }

    public static function plainText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<\s*(p|div|br|img|ul|ol|li|h[1-6]|strong|em|a|span)\b/i', $value);
    }

    public static function isBlank(?string $value): bool
    {
        return self::plainText($value) === '';
    }

    private static function sanitize(string $html): string
    {
        $clean = strip_tags($html, self::ALLOWED_TAGS);
        $clean = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/javascript\s*:/i', '', $clean) ?? $clean;

        $clean = preg_replace_callback(
            '/<(a|img)\b([^>]*)>/i',
            static function (array $matches): string {
                $tag = strtolower($matches[1]);
                $attrs = $matches[2];

                if ($tag === 'a') {
                    $href = self::extractAttr($attrs, 'href');
                    if ($href === null || ! self::isSafeUrl($href)) {
                        return '<a>';
                    }

                    return '<a href="'.e($href).'" rel="noopener noreferrer" target="_blank">';
                }

                $src = self::extractAttr($attrs, 'src');
                if ($src === null || ! self::isSafeImageUrl($src)) {
                    return '';
                }

                $alt = self::extractAttr($attrs, 'alt') ?? '';

                return '<img src="'.e($src).'" alt="'.e($alt).'" class="max-w-full h-auto rounded-lg my-2">';
            },
            $clean,
        ) ?? $clean;

        return $clean;
    }

    private static function extractAttr(string $attrs, string $name): ?string
    {
        if (preg_match('/\b'.preg_quote($name, '/').'\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $m) !== 1) {
            return null;
        }

        return html_entity_decode($m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : $m[4]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        return str_starts_with($url, '/')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, 'http://')
            || str_starts_with($url, 'mailto:');
    }

    private static function isSafeImageUrl(string $url): bool
    {
        $url = trim($url);

        if (str_starts_with($url, '/storage/')) {
            return true;
        }

        return str_starts_with($url, 'https://') || str_starts_with($url, 'http://');
    }
}
