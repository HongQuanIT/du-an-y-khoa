<?php

declare(strict_types=1);

namespace App\Support\Html;

/**
 * Sanitize rich-text from the admin editor and prepare safe HTML for display.
 */
final class SafeHtml
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><h5><h6><blockquote><a><img><sub><sup><span><mark><code><pre><hr><table><thead><tbody><tfoot><tr><th><td>';

    private const CMS_PAGE_TAGS = '<p><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><h4><blockquote><a><img><sub><sup><span><div><section><article><header><footer><hr>';

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

    public static function fromCmsPage(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $html = trim($html);

        if ($html === '' || $html === '<p><br></p>' || $html === '<p></p>') {
            return '';
        }

        return self::sanitizeCmsPage($html);
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

    public static function forCmsPage(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! self::looksLikeHtml($value)) {
            return nl2br(e($value), false);
        }

        return self::sanitizeCmsPage($value);
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
        return (bool) preg_match('/<\s*(p|div|br|img|ul|ol|li|h[1-6]|strong|em|a|span|table|tr|td|th|mark)\b/i', $value);
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

        // Preserve data-hint attribute on <mark> tags (Question Hint feature).
        $clean = preg_replace_callback(
            '/<mark\b([^>]*)>/i',
            static function (array $matches): string {
                $attrs = $matches[1];
                $dataHint = self::extractAttr($attrs, 'data-hint');

                if ($dataHint === 'true') {
                    return '<mark class="ql-hint" data-hint="true">';
                }

                return '<mark>';
            },
            $clean,
        ) ?? $clean;

        // Convert old <span data-hint="true"> to <mark> for backward compatibility,
        // and strip all other attributes from <span> to prevent style/class injection.
        $clean = preg_replace_callback(
            '/<span\b([^>]*)>/i',
            static function (array $matches): string {
                $attrs = $matches[1];
                $dataHint = self::extractAttr($attrs, 'data-hint');

                if ($dataHint === 'true') {
                    return '<mark class="ql-hint" data-hint="true">';
                }

                // Plain <span> without data-hint: strip all attributes
                return '<span>';
            },
            $clean,
        ) ?? $clean;

        return $clean;
    }

    private static function sanitizeCmsPage(string $html): string
    {
        $clean = strip_tags($html, self::CMS_PAGE_TAGS);
        $clean = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/javascript\s*:/i', '', $clean) ?? $clean;

        $clean = preg_replace_callback(
            '/<a\b([^>]*)>/i',
            static function (array $matches): string {
                $attrs = $matches[1];
                $href = self::extractAttr($attrs, 'href');
                if ($href === null || ! self::isSafeUrl($href)) {
                    return '<a>';
                }

                return self::rebuildTag('a', $attrs, [
                    'href' => $href,
                    'rel' => 'noopener noreferrer',
                ]);
            },
            $clean,
        ) ?? $clean;

        $clean = preg_replace_callback(
            '/<img\b([^>]*)>/i',
            static function (array $matches): string {
                $attrs = $matches[1];
                $src = self::extractAttr($attrs, 'src');
                if ($src === null || ! self::isSafeImageUrl($src)) {
                    return '';
                }

                return self::rebuildTag('img', $attrs, [
                    'src' => $src,
                    'alt' => self::extractAttr($attrs, 'alt') ?? '',
                ], true);
            },
            $clean,
        ) ?? $clean;

        return $clean;
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private static function rebuildTag(string $tag, string $attrs, array $overrides, bool $selfClosing = false): string
    {
        $allowed = ['class', 'id', 'title', 'width', 'height', 'loading', 'decoding', 'style', 'aria-label'];
        $parts = [];

        foreach ($overrides as $name => $value) {
            $parts[] = $name.'="'.e($value).'"';
        }

        foreach ($allowed as $name) {
            if (array_key_exists($name, $overrides)) {
                continue;
            }

            $value = self::extractAttr($attrs, $name);
            if ($value !== null && $value !== '') {
                $parts[] = $name.'="'.e($value).'"';
            }
        }

        $joined = implode(' ', $parts);

        return $selfClosing
            ? '<'.$tag.' '.$joined.'>'
            : '<'.$tag.' '.$joined.'>';
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
