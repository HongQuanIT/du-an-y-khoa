<?php

declare(strict_types=1);

namespace Modules\Personalization\Support;

use App\Support\Html\SafeHtml;
use Illuminate\Support\Str;

/** Sanitize learner note rich text and derive a plain-text body. */
final class NoteHtml
{
    /**
     * @return array{body: string, body_html: string}
     */
    public static function fromHtml(?string $html, ?string $plainFallback = null): array
    {
        if ($html !== null && trim($html) !== '') {
            $bodyHtml = self::sanitize($html);
            $body = Str::limit(SafeHtml::plainText($bodyHtml), 5000, '');

            return ['body' => $body, 'body_html' => $bodyHtml];
        }

        $body = Str::limit(trim((string) $plainFallback), 5000, '');

        return [
            'body' => $body,
            'body_html' => $body === '' ? '' : nl2br(e($body)),
        ];
    }

    public static function sanitize(string $html): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><s><ul><ol><li><h3><blockquote><mark><a>';
        $html = preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
        $allowed = strip_tags($html, $allowedTags);
        $allowed = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $allowed) ?? $allowed;
        $allowed = preg_replace('/javascript\s*:/i', '', $allowed) ?? $allowed;
        $allowed = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $allowed) ?? $allowed;
        $allowed = preg_replace('/<a\b(?![^>]*\shref=)([^>]*)>/i', '<a$1>', $allowed) ?? $allowed;
        $allowed = preg_replace_callback('/<a\b([^>]*)>/i', function (array $matches): string {
            $attrs = $matches[1];
            if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $attrs, $href) !== 1) {
                return '<a>';
            }

            $url = $href[1];
            if (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://') && ! str_starts_with($url, 'mailto:')) {
                return '<a>';
            }

            return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">';
        }, $allowed) ?? $allowed;

        return Str::limit(trim($allowed), 20000, '');
    }

    public static function isEmpty(string $body, string $bodyHtml): bool
    {
        return trim(SafeHtml::plainText($bodyHtml !== '' ? $bodyHtml : $body)) === '';
    }
}
