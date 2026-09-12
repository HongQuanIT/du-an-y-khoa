<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Modules\QuestionBank\Enums\Difficulty;

/**
 * Canonical flattened columns for question import/export (one row = one question).
 */
final class QuestionImportSchema
{
    public const MAX_OPTIONS = 5;

    public const FORBIDDEN_FIELDS = [
        'status',
        'publisher_id',
        'published_at',
        'instructor_id',
        'version',
        'published_version',
        'reviewer_id',
    ];

    /**
     * @return array<string, array{label: string, required: bool, aliases: list<string>}>
     */
    public static function fields(): array
    {
        $fields = [
            'code' => [
                'label' => 'Mã câu hỏi (tuỳ chọn)',
                'required' => false,
                'aliases' => ['code', 'ma', 'mã', 'ma cau hoi', 'mã câu hỏi'],
            ],
            'stem' => [
                'label' => 'Đề bài',
                'required' => true,
                'aliases' => ['stem', 'de bai', 'đề bài', 'question', 'vignette', 'noi dung'],
            ],
            'explanation' => [
                'label' => 'Giải thích đáp án đúng',
                'required' => false,
                'aliases' => ['explanation', 'giai thich', 'giải thích', 'giai thich dung'],
            ],
            'correct' => [
                'label' => 'Đáp án đúng (A–E)',
                'required' => true,
                'aliases' => ['correct', 'dap an dung', 'đáp án đúng', 'answer', 'key'],
            ],
            'difficulty' => [
                'label' => 'Độ khó',
                'required' => true,
                'aliases' => ['difficulty', 'do kho', 'độ khó'],
            ],
            'lesson_slugs' => [
                'label' => 'Bài học (slug hoặc mã, cách nhau ;)',
                'required' => true,
                'aliases' => [
                    'lesson_slugs', 'lesson_codes', 'lessons', 'bai hoc', 'bài học',
                    'lesson', 'topic', 'chu de',
                ],
            ],
            'tag_slugs' => [
                'label' => 'Thẻ (slug, cách nhau ;)',
                'required' => false,
                'aliases' => ['tag_slugs', 'tags', 'the', 'thẻ'],
            ],
            'is_free' => [
                'label' => 'Miễn phí (0/1)',
                'required' => false,
                'aliases' => ['is_free', 'free', 'mien phi'],
            ],
            'exam_flag' => [
                'label' => 'Pool thi (0/1)',
                'required' => false,
                'aliases' => ['exam_flag', 'exam', 'thi'],
            ],
            'attending_tip' => [
                'label' => 'Gợi ý giảng viên',
                'required' => false,
                'aliases' => ['attending_tip', 'tip', 'goi y'],
            ],
            'hints' => [
                'label' => 'Kiến thức / gợi ý (| hoặc xuống dòng)',
                'required' => false,
                'aliases' => ['hints', 'key_info', 'kien thuc'],
            ],
        ];

        foreach (range(0, self::MAX_OPTIONS - 1) as $index) {
            $letter = chr(65 + $index);
            $lower = strtolower($letter);
            $fields['option_'.$lower] = [
                'label' => 'Đáp án '.$letter,
                'required' => $index < 2,
                'aliases' => [
                    'option_'.$lower,
                    'dap an '.$lower,
                    'đáp án '.$lower,
                    $lower,
                ],
            ];
            $fields['option_'.$lower.'_explanation'] = [
                'label' => 'Giải thích đáp án '.$letter,
                'required' => false,
                'aliases' => [
                    'option_'.$lower.'_explanation',
                    'giai thich '.$lower,
                    'giải thích '.$letter,
                    $lower.'_explanation',
                ],
            ];
        }

        return $fields;
    }

    /** @return list<string> */
    public static function headers(): array
    {
        return array_keys(self::fields());
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    public static function autoMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $field = self::matchField((string) $header);
            if ($field === null || isset($map[$field])) {
                continue;
            }
            $map[$field] = $index;
        }

        return $map;
    }

    public static function matchField(string $header): ?string
    {
        $normalized = self::normalize($header);
        if ($normalized === '' || in_array($normalized, self::FORBIDDEN_FIELDS, true)) {
            return null;
        }

        foreach (self::fields() as $field => $meta) {
            if ($normalized === self::normalize($field)) {
                return $field;
            }
            foreach ($meta['aliases'] as $alias) {
                if ($normalized === self::normalize($alias)) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, int|string|null>  $map
     * @return array<string, string>
     */
    public static function extractRow(array $row, array $map): array
    {
        $values = [];
        foreach (array_keys(self::fields()) as $field) {
            $index = $map[$field] ?? null;
            if ($index === null || $index === '') {
                $values[$field] = '';

                continue;
            }

            $values[$field] = trim((string) ($row[(int) $index] ?? ''));
        }

        return $values;
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
            'đ' => 'd',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function parseDifficulty(string $raw): ?Difficulty
    {
        $normalized = self::normalize($raw);

        return match ($normalized) {
            'very easy', 'very_easy', 'rat de' => Difficulty::VeryEasy,
            'easy', 'de' => Difficulty::Easy,
            'medium', 'trung binh', 'tb' => Difficulty::Medium,
            'hard', 'kho' => Difficulty::Hard,
            'very hard', 'very_hard', 'rat kho' => Difficulty::VeryHard,
            default => Difficulty::tryFrom($raw) ?? Difficulty::tryFrom($normalized),
        };
    }

    public static function parseBoolean(string $raw, bool $default = false): bool
    {
        $normalized = self::normalize($raw);
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'co', 'x'], true);
    }

    /**
     * @return list<string>
     */
    public static function splitList(string $raw): array
    {
        $parts = preg_split('/[;,\n|]+/u', $raw) ?: [];

        return collect($parts)
            ->map(fn (string $part): string => trim($part))
            ->filter(fn (string $part): bool => $part !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function sampleRow(): array
    {
        $row = array_fill_keys(self::headers(), '');
        $row['stem'] = 'Bệnh nhân 55 tuổi đau ngực. Chẩn đoán nào phù hợp nhất?';
        $row['option_a'] = 'Hội chứng mạch vành cấp';
        $row['option_b'] = 'Trào ngược dạ dày';
        $row['option_c'] = 'Lo lắng';
        $row['option_d'] = 'Viêm phổi';
        $row['option_a_explanation'] = 'Đau ngực + yếu tố nguy cơ gợi ý ACS.';
        $row['option_b_explanation'] = 'Thường nóng rát sau xương ức, liên quan bữa ăn.';
        $row['correct'] = 'A';
        $row['explanation'] = 'Đau ngực + yếu tố nguy cơ gợi ý ACS.';
        $row['difficulty'] = 'medium';
        $row['lesson_slugs'] = 'tim-mach-admin-test';
        $row['is_free'] = '0';
        $row['exam_flag'] = '0';

        return array_values($row);
    }

    /** @return list<list<string>> */
    public static function guideRows(): array
    {
        return [
            ['Trường', 'Bắt buộc', 'Ghi chú'],
            ['stem', 'Có', 'Đề bài. Không nhúng đáp án A/B cứng.'],
            ['option_a … option_e', '≥2 đáp án', 'Để trống cột nếu không dùng.'],
            ['correct', 'Có', 'Một chữ: A, B, C, D hoặc E. Single best answer.'],
            ['difficulty', 'Có', 'very_easy | easy | medium | hard | very_hard'],
            ['lesson_slugs', 'Có', 'Slug hoặc mã bài học đã có trên hệ thống, cách nhau ;'],
            ['explanation', 'Khuyến nghị', 'Bắt buộc trước khi gửi duyệt. Import vẫn tạo nháp nếu thiếu.'],
            ['status / publisher_id / version', 'Cấm', 'Hệ thống bỏ qua. Import luôn tạo bản nháp (draft).'],
            ['code', 'Không', 'Để trống để hệ thống cấp Q00001… Trùng mã = lỗi dòng.'],
        ];
    }
}
