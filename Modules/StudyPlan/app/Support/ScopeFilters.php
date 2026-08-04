<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Support;

/**
 * Static catalogs for the Amboss-style "Phạm vi ôn tập" picker.
 *
 * These mirror the Q-Bank custom-session filter rows until a real taxonomy
 * module owns exams / articles / symptoms.
 */
final class ScopeFilters
{
    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function examTags(): array
    {
        $tags = [];

        foreach (TargetExams::selectable() as $id => $exam) {
            $tags[] = ['id' => $id, 'name' => $exam['title']];
        }

        return $tags;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function articles(): array
    {
        return self::named([
            'abcde-approach' => 'ABCDE approach',
            'acs' => 'Acute coronary syndromes',
            'heart-failure' => 'Heart failure',
            'pneumonia' => 'Pneumonia',
            'sepsis' => 'Sepsis',
            'stroke' => 'Stroke',
        ]);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function symptoms(): array
    {
        return self::named([
            'chest-pain' => 'Đau ngực',
            'dyspnea' => 'Khó thở',
            'fever' => 'Sốt',
            'abdominal-pain' => 'Đau bụng',
            'headache' => 'Đau đầu',
            'syncope' => 'Ngất',
        ]);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function difficulties(): array
    {
        return self::named([
            'easy' => 'Dễ',
            'medium' => 'Trung bình',
            'hard' => 'Khó',
        ]);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function questionStatuses(): array
    {
        return self::named([
            'unanswered' => 'Chưa trả lời',
            'correct_with_hints' => 'Trả lời đúng có dùng gợi ý',
            'incorrect' => 'Trả lời sai',
            'correct' => 'Trả lời đúng',
        ]);
    }

    /**
     * @param  array<string, string>  $map
     * @return array<int, array{id: string, name: string}>
     */
    private static function named(array $map): array
    {
        $items = [];

        foreach ($map as $id => $name) {
            $items[] = ['id' => $id, 'name' => $name];
        }

        return $items;
    }
}
