<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

/**
 * Editorial checks a reviewer walks through before marking Đạt / Không đạt.
 */
final class ReviewerChecklist
{
    /**
     * @return list<array{key: string, title: string, detail: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'options',
                'title' => 'Đủ đáp án',
                'detail' => 'Câu có đủ 4 đáp án và đúng một đáp án đúng.',
            ],
            [
                'key' => 'spelling',
                'title' => 'Chính tả',
                'detail' => 'Câu hỏi và đáp án không sai chính tả.',
            ],
            [
                'key' => 'terminology',
                'title' => 'Thuật ngữ',
                'detail' => 'Không dùng thuật ngữ sai, hoặc cách gọi gây khó hiểu.',
            ],
            [
                'key' => 'hints',
                'title' => 'Gợi ý khớp đề',
                'detail' => 'Có gợi ý, và từng gợi ý khớp một cụm từ trong câu hỏi.',
            ],
            [
                'key' => 'media',
                'title' => 'Hình ảnh',
                'detail' => 'Nếu có hình: rõ, đúng nội dung câu, không chứa chữ lộ đáp án.',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::definitions(), 'key');
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function only(array $keys): array
    {
        $allowed = array_flip(self::keys());

        $selected = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            if (isset($allowed[$key])) {
                $selected[$key] = $key;
            }
        }

        return array_values($selected);
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function titles(array $keys): array
    {
        $selected = array_flip(self::only($keys));
        $titles = [];
        foreach (self::definitions() as $item) {
            if (isset($selected[$item['key']])) {
                $titles[] = $item['title'];
            }
        }

        return $titles;
    }
}
