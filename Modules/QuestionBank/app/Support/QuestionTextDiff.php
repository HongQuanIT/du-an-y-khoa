<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use App\Support\Html\SafeHtml;

/**
 * Word-level split-view highlighter for published vs proposed question text.
 * Rich HTML is kept for display; comparison still uses plain-text normalize.
 */
final class QuestionTextDiff
{
    private const MAX_TOKENS = 1500;

    /**
     * @return array{changed: bool, published_html: string, proposed_html: string}
     */
    public function highlight(?string $published, ?string $proposed): array
    {
        $leftRaw = (string) ($published ?? '');
        $rightRaw = (string) ($proposed ?? '');
        $left = $this->normalize($leftRaw);
        $right = $this->normalize($rightRaw);

        if ($left === $right) {
            return [
                'changed' => false,
                'published_html' => $this->display($leftRaw),
                'proposed_html' => $this->display($rightRaw !== '' ? $rightRaw : $leftRaw),
            ];
        }

        if (SafeHtml::looksLikeHtml($leftRaw) || SafeHtml::looksLikeHtml($rightRaw)) {
            return [
                'changed' => true,
                'published_html' => $left === '' ? '' : $this->display($leftRaw),
                'proposed_html' => $right === '' ? '' : $this->display($rightRaw),
            ];
        }

        if ($left === '' || $right === '') {
            return [
                'changed' => true,
                'published_html' => $left === '' ? '' : $this->wrap($left, 'del'),
                'proposed_html' => $right === '' ? '' : $this->wrap($right, 'ins'),
            ];
        }

        $leftTokens = $this->tokenize($left);
        $rightTokens = $this->tokenize($right);

        if (count($leftTokens) > self::MAX_TOKENS || count($rightTokens) > self::MAX_TOKENS) {
            return [
                'changed' => true,
                'published_html' => $this->wrap($left, 'del'),
                'proposed_html' => $this->wrap($right, 'ins'),
            ];
        }

        [$publishedHtml, $proposedHtml] = $this->render($leftTokens, $rightTokens);

        return [
            'changed' => true,
            'published_html' => $publishedHtml,
            'proposed_html' => $proposedHtml,
        ];
    }

    public function normalize(?string $value): string
    {
        $plain = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plain = preg_replace('/[ \t]+/u', ' ', $plain) ?? $plain;

        return trim($plain);
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $parts = preg_split(
            '/(\s+|[.,;:!?()\[\]{}\"\'“”‘’…\/]+)/u',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        return is_array($parts) ? array_values($parts) : [$text];
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @return array{0: string, 1: string}
     */
    private function render(array $left, array $right): array
    {
        $ops = $this->opcodes($left, $right);
        $published = '';
        $proposed = '';

        foreach ($ops as $op) {
            if ($op['tag'] === 'equal') {
                $chunk = e(implode('', array_slice($left, $op['i1'], $op['i2'] - $op['i1'])));
                $published .= $chunk;
                $proposed .= $chunk;

                continue;
            }

            if ($op['tag'] === 'delete' || $op['tag'] === 'replace') {
                $chunk = implode('', array_slice($left, $op['i1'], $op['i2'] - $op['i1']));
                if ($chunk !== '') {
                    $published .= $this->wrap($chunk, 'del');
                }
            }

            if ($op['tag'] === 'insert' || $op['tag'] === 'replace') {
                $chunk = implode('', array_slice($right, $op['j1'], $op['j2'] - $op['j1']));
                if ($chunk !== '') {
                    $proposed .= $this->wrap($chunk, 'ins');
                }
            }
        }

        return [$published, $proposed];
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @return list<array{tag: string, i1: int, i2: int, j1: int, j2: int}>
     */
    private function opcodes(array $left, array $right): array
    {
        $n = count($left);
        $m = count($right);
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $left[$i] === $right[$j]
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;

        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $left[$i] === $right[$j]) {
                $ops[] = ['tag' => 'equal', 'i1' => $i, 'i2' => $i + 1, 'j1' => $j, 'j2' => $j + 1];
                $i++;
                $j++;

                continue;
            }

            if ($j < $m && ($i === $n || $dp[$i][$j + 1] >= $dp[$i + 1][$j])) {
                $ops[] = ['tag' => 'insert', 'i1' => $i, 'i2' => $i, 'j1' => $j, 'j2' => $j + 1];
                $j++;

                continue;
            }

            $ops[] = ['tag' => 'delete', 'i1' => $i, 'i2' => $i + 1, 'j1' => $j, 'j2' => $j];
            $i++;
        }

        return $this->coalesce($ops);
    }

    /**
     * @param  list<array{tag: string, i1: int, i2: int, j1: int, j2: int}>  $ops
     * @return list<array{tag: string, i1: int, i2: int, j1: int, j2: int}>
     */
    private function coalesce(array $ops): array
    {
        $merged = [];

        foreach ($ops as $op) {
            $last = $merged === [] ? null : $merged[array_key_last($merged)];
            if ($last !== null && $last['tag'] !== 'equal' && $op['tag'] !== 'equal') {
                $merged[array_key_last($merged)] = [
                    'tag' => 'replace',
                    'i1' => $last['i1'],
                    'i2' => $op['i2'],
                    'j1' => $last['j1'],
                    'j2' => $op['j2'],
                ];

                continue;
            }

            $merged[] = $op;
        }

        return $merged;
    }

    private function display(string $value): string
    {
        return SafeHtml::forDisplay($value);
    }

    private function wrap(string $text, string $tag): string
    {
        $class = $tag === 'del' ? 'question-diff-del' : 'question-diff-ins';

        return '<'.$tag.' class="'.$class.'">'.e($text).'</'.$tag.'>';
    }
}
