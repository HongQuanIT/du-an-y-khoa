<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/** Shared catalog of target exams used by study plans and Q-Bank filters. */
final class TargetExams
{
    /**
     * @return array<string, array{title: string, icon: string, hint: string, legacy?: bool}>
     */
    public static function all(): array
    {
        return [
            'resident' => [
                'title' => 'Bác sĩ nội trú',
                'icon' => 'stethoscope',
                'hint' => 'Dành cho sinh viên Y6 chuẩn bị thi BSCKNT.',
            ],
            'course' => [
                'title' => 'Thi hết học phần',
                'icon' => 'menu_book',
                'hint' => 'Ôn thi các môn lâm sàng & cận lâm sàng.',
            ],
            'usmle-step-1' => [
                'title' => 'USMLE Step 1',
                'icon' => 'workspace_premium',
                'hint' => 'Chuẩn bị cho kỳ thi cấp phép hành nghề Mỹ — Step 1.',
            ],
            'usmle-step-2-ck' => [
                'title' => 'USMLE Step 2 CK',
                'icon' => 'clinical_notes',
                'hint' => 'Clinical Knowledge — kỹ năng lâm sàng và ra quyết định.',
            ],
            'usmle-step-3' => [
                'title' => 'USMLE Step 3',
                'icon' => 'medical_services',
                'hint' => 'Bước cuối trước khi hành nghề độc lập tại Mỹ.',
            ],
            'nbme' => [
                'title' => 'NBME',
                'icon' => 'quiz',
                'hint' => 'Đề thi thử / self-assessment theo chuẩn NBME.',
            ],
            'usmle' => [
                'title' => 'USMLE Step 1',
                'icon' => 'workspace_premium',
                'hint' => 'Chuẩn bị cho kỳ thi cấp phép hành nghề Mỹ.',
                'legacy' => true,
            ],
        ];
    }

    /**
     * @return array<string, array{title: string, icon: string, hint: string}>
     */
    public static function selectable(): array
    {
        return array_filter(
            self::all(),
            fn (array $exam): bool => empty($exam['legacy']),
        );
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function title(?string $key): string
    {
        return self::all()[$key]['title'] ?? 'Kế hoạch học tập';
    }

    /** Label for account UI; shows a neutral placeholder when unset. */
    public static function displayTitle(?string $key): string
    {
        if ($key === null || $key === '' || ! isset(self::all()[$key])) {
            return 'Chưa chọn';
        }

        return self::all()[$key]['title'];
    }

    public static function planName(string $key, Carbon $targetDate): string
    {
        return 'Ôn thi '.self::title($key).' '.$targetDate->year;
    }

    public static function filterTag(string $key): string
    {
        return $key === 'usmle' ? 'usmle-step-1' : $key;
    }
}
