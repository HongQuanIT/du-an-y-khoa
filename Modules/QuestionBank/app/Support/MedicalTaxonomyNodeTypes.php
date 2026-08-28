<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

/**
 * Phân loại mục trong danh mục y khoa (Medical Knowledge Taxonomy).
 *
 * Kiến trúc tham chiếu AMBOSS:
 * - Cây chính: Hệ cơ quan → Chuyên khoa → Bệnh / Tình trạng
 * - Biểu hiện lâm sàng: Triệu chứng, Dấu hiệu (gắn chéo với câu hỏi)
 * - Cận lâm sàng: Phát hiện lâm sàng, Xét nghiệm, Hình ảnh
 * - Kiến thức & can thiệp: Khái niệm, Thủ thuật, Thuốc
 */
final class MedicalTaxonomyNodeTypes
{
    /** @var array<string, string> */
    public const LABELS = [
        'system' => 'Hệ cơ quan',
        'specialty' => 'Chuyên khoa',
        'disease' => 'Bệnh',
        'condition' => 'Tình trạng / Hội chứng',
        'symptom' => 'Triệu chứng',
        'sign' => 'Dấu hiệu',
        'clinical_finding' => 'Phát hiện lâm sàng',
        'lab_finding' => 'Kết quả xét nghiệm',
        'imaging_finding' => 'Kết quả hình ảnh',
        'concept' => 'Khái niệm',
        'procedure' => 'Thủ thuật',
        'drug' => 'Thuốc',
        'other' => 'Khác',
    ];

    /**
     * Nhóm quản lý trên UI — gom các loại liên quan.
     *
     * @var array<string, array{label: string, description: string, icon: string, types: list<string>}>
     */
    public const GROUPS = [
        'structure' => [
            'label' => 'Phân loại',
            'description' => 'Cây phân cấp chính theo hệ cơ quan và chuyên khoa, đến bệnh / hội chứng.',
            'icon' => 'account_tree',
            'types' => ['system', 'specialty', 'disease', 'condition'],
        ],
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
            'types' => ['concept', 'procedure', 'drug'],
        ],
        'other' => [
            'label' => 'Khác',
            'description' => 'Mục chưa phân loại.',
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

    /**
     * @param  array<string, int>  $countsByType
     * @return array<string, array{label: string, description: string, icon: string, count: int, types: array<string, int>}>
     */
    public static function groupedStats(array $countsByType): array
    {
        $result = [];

        foreach (self::GROUPS as $key => $group) {
            $types = [];
            $count = 0;

            foreach ($group['types'] as $type) {
                $c = (int) ($countsByType[$type] ?? 0);
                if ($c > 0) {
                    $types[$type] = $c;
                    $count += $c;
                }
            }

            if ($count > 0) {
                $result[$key] = [
                    'label' => $group['label'],
                    'description' => $group['description'],
                    'icon' => $group['icon'],
                    'count' => $count,
                    'types' => $types,
                ];
            }
        }

        return $result;
    }
}
