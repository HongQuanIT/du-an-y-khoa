<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

/**
 * Trục nhãn (tags) — song song với danh mục 3 cấp (bài học / môn học / hệ cơ quan).
 *
 * Hấp thụ các biểu hiện lâm sàng, cận lâm sàng và khái niệm/can thiệp mà trước
 * đây được mô tả bằng node_type phi cấu trúc.
 */
final class TagType
{
    /** @var array<string, string> */
    public const LABELS = [
        'symptom' => 'Triệu chứng',
        'sign' => 'Dấu hiệu',
        'clinical_finding' => 'Phát hiện lâm sàng',
        'lab_finding' => 'Kết quả xét nghiệm',
        'imaging_finding' => 'Kết quả hình ảnh',
        'concept' => 'Khái niệm',
        'procedure' => 'Thủ thuật',
        'drug' => 'Thuốc',
        'high_yield' => 'Trọng tâm (High-yield)',
        'other' => 'Khác',
    ];

    /**
     * Nhóm quản lý trên UI — gom các loại nhãn liên quan.
     *
     * @var array<string, array{label: string, description: string, icon: string, types: list<string>}>
     */
    public const GROUPS = [
        'presentation' => [
            'label' => 'Biểu hiện lâm sàng',
            'description' => 'Những gì người bệnh kể hoặc bác sĩ quan sát được khi khám.',
            'icon' => 'symptoms',
            'types' => ['symptom', 'sign'],
        ],
        'diagnostics' => [
            'label' => 'Cận lâm sàng',
            'description' => 'Kết quả khám, xét nghiệm và chẩn đoán hình ảnh.',
            'icon' => 'biotech',
            'types' => ['clinical_finding', 'lab_finding', 'imaging_finding'],
        ],
        'knowledge' => [
            'label' => 'Kiến thức & can thiệp',
            'description' => 'Khái niệm cần nắm, thủ thuật và thuốc liên quan.',
            'icon' => 'school',
            'types' => ['concept', 'procedure', 'drug', 'high_yield'],
        ],
        'other' => [
            'label' => 'Khác',
            'description' => 'Nhãn chưa phân loại.',
            'icon' => 'more_horiz',
            'types' => ['other'],
        ],
    ];

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(?string $type): string
    {
        if ($type === null || $type === '') {
            return self::LABELS['other'];
        }

        return self::LABELS[$type] ?? $type;
    }

    public static function groupKey(?string $type): string
    {
        $type = $type ?: 'other';

        foreach (self::GROUPS as $key => $group) {
            if (in_array($type, $group['types'], true)) {
                return $key;
            }
        }

        return 'other';
    }

    /** @return array{label: string, description: string, icon: string, types: list<string>} */
    public static function group(?string $type): array
    {
        return self::GROUPS[self::groupKey($type)];
    }
}
