<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use App\Support\Html\SafeHtml;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Convert editor HTML ↔ Excel shared-string runs so XLSX keeps bold/italic/breaks.
 */
final class SpreadsheetRichText
{
    /**
     * @return list<array{text: string, bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string, literal: bool}>
     */
    public static function fromHtml(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [self::run('')];
        }

        if (! SafeHtml::looksLikeHtml($html)) {
            return [self::run(self::sanitizeText($html))];
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $wrapped = '<div id="rt-root">'.$html.'</div>';
        $document->loadHTML(
            '<?xml encoding="UTF-8">'.self::encodeNonAscii($wrapped),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = self::rootElement($document);
        $runs = [];
        $ctx = ['list' => null, 'ol' => 0];
        if ($root instanceof DOMNode) {
            self::walk($root, self::style(), $runs, $ctx);
        }

        $runs = self::merge(self::trimRuns($runs));

        return $runs === [] ? [self::run('')] : $runs;
    }

    /**
     * @param  list<array{text: string, bold?: bool, italic?: bool, underline?: bool, strike?: bool, vertAlign?: string, literal?: bool}>  $runs
     */
    public static function toHtml(array $runs): string
    {
        $html = '';

        foreach ($runs as $run) {
            $text = (string) ($run['text'] ?? '');
            if ($text === '') {
                continue;
            }

            if (($run['literal'] ?? false) === true || preg_match('/^<img\b/i', $text) === 1) {
                $html .= $text;

                continue;
            }

            $chunk = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $chunk = nl2br($chunk, false);

            if ($run['bold'] ?? false) {
                $chunk = '<strong>'.$chunk.'</strong>';
            }
            if ($run['italic'] ?? false) {
                $chunk = '<em>'.$chunk.'</em>';
            }
            if ($run['underline'] ?? false) {
                $chunk = '<u>'.$chunk.'</u>';
            }
            if ($run['strike'] ?? false) {
                $chunk = '<s>'.$chunk.'</s>';
            }
            if (($run['vertAlign'] ?? '') === 'subscript') {
                $chunk = '<sub>'.$chunk.'</sub>';
            }
            if (($run['vertAlign'] ?? '') === 'superscript') {
                $chunk = '<sup>'.$chunk.'</sup>';
            }

            $html .= $chunk;
        }

        return $html;
    }

    /**
     * @param  list<array{text: string, bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string, literal: bool}>  $runs
     */
    public static function hasInlineStyle(array $runs): bool
    {
        foreach ($runs as $run) {
            if ($run['bold'] || $run['italic'] || $run['underline'] || $run['strike'] || $run['vertAlign'] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{text: string, bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string, literal: bool}>  $runs
     */
    public static function plainText(array $runs): string
    {
        $text = '';
        foreach ($runs as $run) {
            $text .= $run['text'];
        }

        return $text;
    }

    /**
     * @param  array{bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string}  $style
     * @param  list<array{text: string, bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string, literal: bool}>  $runs
     * @param  array{list: ?string, ol: int}  $ctx
     */
    private static function walk(DOMNode $node, array $style, array &$runs, array &$ctx): void
    {
        if ($node instanceof DOMText) {
            $text = $node->textContent;
            if (trim($text) === '') {
                if (str_contains($text, ' ')) {
                    $runs[] = self::run(' ', $style);
                }

                return;
            }

            $runs[] = self::run(self::sanitizeText($text), $style);

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);
        if ($tag === 'rt-root' || $tag === 'div' && $node->getAttribute('id') === 'rt-root') {
            foreach ($node->childNodes as $child) {
                self::walk($child, $style, $runs, $ctx);
            }

            return;
        }

        if ($tag === 'br') {
            $runs[] = self::run("\n", $style);

            return;
        }

        if ($tag === 'img') {
            $src = trim($node->getAttribute('src'));
            $alt = trim($node->getAttribute('alt'));
            if ($src !== '') {
                $runs[] = self::run(
                    '<img src="'.htmlspecialchars($src, ENT_QUOTES | ENT_HTML5, 'UTF-8').'" alt="'
                    .htmlspecialchars($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8').'">',
                    $style,
                    literal: true,
                );
            }

            return;
        }

        if (in_array($tag, ['p', 'div', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote'], true)) {
            self::emitBlockBreak($runs, $style);
            $next = $style;
            if (str_starts_with($tag, 'h')) {
                $next['bold'] = true;
            }
            foreach ($node->childNodes as $child) {
                self::walk($child, $next, $runs, $ctx);
            }

            return;
        }

        if ($tag === 'ul' || $tag === 'ol') {
            $childCtx = $ctx;
            $childCtx['list'] = $tag;
            $childCtx['ol'] = 0;
            foreach ($node->childNodes as $child) {
                self::walk($child, $style, $runs, $childCtx);
            }

            return;
        }

        if ($tag === 'li') {
            self::emitBlockBreak($runs, $style);
            if (($ctx['list'] ?? null) === 'ol') {
                $ctx['ol'] = ((int) ($ctx['ol'] ?? 0)) + 1;
                $runs[] = self::run($ctx['ol'].'. ', $style);
            } else {
                $runs[] = self::run('• ', $style);
            }
            foreach ($node->childNodes as $child) {
                self::walk($child, $style, $runs, $ctx);
            }

            return;
        }

        $next = $style;
        if (in_array($tag, ['strong', 'b'], true)) {
            $next['bold'] = true;
        }
        if (in_array($tag, ['em', 'i'], true)) {
            $next['italic'] = true;
        }
        if ($tag === 'u') {
            $next['underline'] = true;
        }
        if (in_array($tag, ['s', 'strike', 'del'], true)) {
            $next['strike'] = true;
        }
        if ($tag === 'sub') {
            $next['vertAlign'] = 'subscript';
        }
        if ($tag === 'sup') {
            $next['vertAlign'] = 'superscript';
        }
        if ($tag === 'a') {
            $next['underline'] = true;
        }

        foreach ($node->childNodes as $child) {
            self::walk($child, $next, $runs, $ctx);
        }
    }

    /**
     * @param  list<array{text: string, bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string, literal: bool}>  $runs
     * @param  array{bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string}  $style
     */
    private static function emitBlockBreak(array &$runs, array $style): void
    {
        if ($runs === []) {
            return;
        }

        $last = $runs[array_key_last($runs)]['text'] ?? '';
        if (! str_ends_with($last, "\n")) {
            $runs[] = self::run("\n", $style);
        }
    }

    /**
     * @param  array{bold?: bool, italic?: bool, underline?: bool, strike?: bool, vertAlign?: string}  $style
     * @return array{text: string, bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string, literal: bool}
     */
    private static function run(string $text, array $style = [], bool $literal = false): array
    {
        $base = self::style();

        return [
            'text' => $text,
            'bold' => (bool) ($style['bold'] ?? $base['bold']),
            'italic' => (bool) ($style['italic'] ?? $base['italic']),
            'underline' => (bool) ($style['underline'] ?? $base['underline']),
            'strike' => (bool) ($style['strike'] ?? $base['strike']),
            'vertAlign' => (string) ($style['vertAlign'] ?? $base['vertAlign']),
            'literal' => $literal,
        ];
    }

    /**
     * @return array{bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string}
     */
    private static function style(): array
    {
        return [
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'strike' => false,
            'vertAlign' => '',
        ];
    }

    /**
     * @param  list<array{text: string, bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string, literal: bool}>  $runs
     * @return list<array{text: string, bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string, literal: bool}>
     */
    private static function merge(array $runs): array
    {
        $merged = [];
        foreach ($runs as $run) {
            if ($run['text'] === '') {
                continue;
            }
            $last = $merged === [] ? null : $merged[array_key_last($merged)];
            if ($last !== null
                && $last['literal'] === $run['literal']
                && $last['literal'] === false
                && $last['bold'] === $run['bold']
                && $last['italic'] === $run['italic']
                && $last['underline'] === $run['underline']
                && $last['strike'] === $run['strike']
                && $last['vertAlign'] === $run['vertAlign']
            ) {
                $merged[array_key_last($merged)]['text'] .= $run['text'];

                continue;
            }
            $merged[] = $run;
        }

        return $merged;
    }

    /**
     * @param  list<array{text: string, bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string, literal: bool}>  $runs
     * @return list<array{text: string, bold: bool, italic: bool, underline: bool, strike: bool, vertAlign: string, literal: bool}>
     */
    private static function trimRuns(array $runs): array
    {
        while ($runs !== [] && trim($runs[0]['text']) === '' && ! str_contains($runs[0]['text'], ' ')) {
            array_shift($runs);
        }
        while ($runs !== [] && rtrim($runs[array_key_last($runs)]['text'], "\n") === '' && $runs[array_key_last($runs)]['text'] !== '') {
            $last = array_key_last($runs);
            $runs[$last]['text'] = rtrim($runs[$last]['text'], "\n");
            if ($runs[$last]['text'] === '') {
                array_pop($runs);
            }
        }

        return $runs;
    }

    private static function sanitizeText(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;
    }

    private static function encodeNonAscii(string $html): string
    {
        return mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0xFFFF], 'UTF-8');
    }

    private static function rootElement(DOMDocument $document): ?DOMNode
    {
        foreach ($document->getElementsByTagName('div') as $div) {
            if ($div->getAttribute('id') === 'rt-root') {
                return $div;
            }
        }

        return $document->documentElement;
    }
}
