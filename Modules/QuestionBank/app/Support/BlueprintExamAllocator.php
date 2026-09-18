<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;

/**
 * Derive exam question quotas from a blueprint weight matrix.
 *
 * Section share uses the midpoint of weight_min/weight_max; topic share uses weight %.
 * Largest-remainder rounding keeps the grand total equal to blueprint.total_questions.
 */
final class BlueprintExamAllocator
{
    /**
     * @return array{
     *     ready: bool,
     *     reason: string|null,
     *     blueprint: array{id: int, name: string, slug: string, code: string|null, description: string|null, total_questions: int|null},
     *     total_questions: int,
     *     suggested_duration_minutes: int,
     *     section_count: int,
     *     topic_count: int,
     *     sections: list<array{
     *         id: int,
     *         name: string,
     *         weight_min: float|null,
     *         weight_max: float|null,
     *         question_count: int,
     *         topics: list<array{
     *             id: int,
     *             name: string,
     *             weight: float|null,
     *             question_count: int,
     *             sort_order: int
     *         }>
     *     }>
     * }
     */
    public function allocate(Blueprint $blueprint): array
    {
        $blueprint->loadMissing([
            'sections' => fn ($query) => $query
                ->where('status', TaxonomyStatus::Active)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->with(['coreClinicalTopics' => fn ($topicQuery) => $topicQuery
                    ->where('status', TaxonomyStatus::Active)
                    ->orderBy('sort_order')
                    ->orderBy('name')]),
        ]);

        $total = (int) ($blueprint->total_questions ?? 0);
        $sections = $blueprint->sections;

        $base = [
            'blueprint' => [
                'id' => (int) $blueprint->id,
                'name' => $blueprint->name,
                'slug' => $blueprint->slug,
                'code' => $blueprint->code,
                'description' => $blueprint->description,
                'total_questions' => $blueprint->total_questions,
            ],
            'total_questions' => max(0, $total),
            'suggested_duration_minutes' => $this->suggestedDurationMinutes($total),
            'section_count' => $sections->count(),
            'topic_count' => $sections->sum(fn ($section) => $section->coreClinicalTopics->count()),
            'sections' => [],
        ];

        if ($total <= 0) {
            return array_merge($base, [
                'ready' => false,
                'reason' => 'Ma trận chưa cấu hình tổng số câu. Mở trang ma trận để nhập tổng số câu và tỉ trọng.',
            ]);
        }

        if ($sections->isEmpty()) {
            return array_merge($base, [
                'ready' => false,
                'reason' => 'Ma trận chưa có phần (section) nào.',
            ]);
        }

        $sectionShares = [];
        foreach ($sections as $section) {
            $mid = $this->sectionMidpointPercent($section->weight_min, $section->weight_max);
            if ($mid === null) {
                return array_merge($base, [
                    'ready' => false,
                    'reason' => sprintf('Phần «%s» chưa có tỉ trọng min/max.', $section->name),
                ]);
            }
            $sectionShares[] = [
                'section' => $section,
                'share' => $mid,
            ];
        }

        $sectionCounts = $this->distributeByShare(
            $total,
            array_map(fn (array $row): float => $row['share'], $sectionShares),
        );

        $payloadSections = [];
        $allocatedTotal = 0;

        foreach ($sectionShares as $index => $row) {
            $section = $row['section'];
            $sectionQuota = $sectionCounts[$index];
            $topics = $section->coreClinicalTopics;

            if ($topics->isEmpty()) {
                return array_merge($base, [
                    'ready' => false,
                    'reason' => sprintf('Phần «%s» chưa có chủ đề lâm sàng.', $section->name),
                ]);
            }

            $topicShares = [];
            foreach ($topics as $topic) {
                if ($topic->weight === null) {
                    return array_merge($base, [
                        'ready' => false,
                        'reason' => sprintf('Chủ đề «%s» chưa có tỉ trọng.', $topic->name),
                    ]);
                }
                $topicShares[] = [
                    'topic' => $topic,
                    'share' => (float) $topic->weight,
                ];
            }

            $topicCounts = $this->distributeByShare(
                $sectionQuota,
                array_map(fn (array $item): float => $item['share'], $topicShares),
            );

            $topicPayload = [];
            foreach ($topicShares as $topicIndex => $topicRow) {
                $count = $topicCounts[$topicIndex];
                $topicPayload[] = [
                    'id' => (int) $topicRow['topic']->id,
                    'name' => $topicRow['topic']->name,
                    'weight' => $topicRow['topic']->weight !== null ? (float) $topicRow['topic']->weight : null,
                    'question_count' => $count,
                    'sort_order' => (int) $topicRow['topic']->sort_order,
                ];
                $allocatedTotal += $count;
            }

            $payloadSections[] = [
                'id' => (int) $section->id,
                'name' => $section->name,
                'weight_min' => $section->weight_min !== null ? (float) $section->weight_min : null,
                'weight_max' => $section->weight_max !== null ? (float) $section->weight_max : null,
                'question_count' => $sectionQuota,
                'topics' => $topicPayload,
            ];
        }

        return array_merge($base, [
            'ready' => true,
            'reason' => null,
            'total_questions' => $allocatedTotal,
            'suggested_duration_minutes' => $this->suggestedDurationMinutes($allocatedTotal),
            'sections' => $payloadSections,
        ]);
    }

    public function suggestedDurationMinutes(int $totalQuestions): int
    {
        if ($totalQuestions <= 0) {
            return 90;
        }

        // ~1.5 phút/câu, làm tròn bội 5 — gần cấu hình đề thi chuẩn.
        return max(30, (int) (round(($totalQuestions * 1.5) / 5) * 5));
    }

    private function sectionMidpointPercent(mixed $min, mixed $max): ?float
    {
        $minVal = is_numeric($min) ? (float) $min : null;
        $maxVal = is_numeric($max) ? (float) $max : null;

        if ($minVal === null && $maxVal === null) {
            return null;
        }

        if ($minVal === null) {
            return $maxVal;
        }

        if ($maxVal === null) {
            return $minVal;
        }

        return ($minVal + $maxVal) / 2;
    }

    /**
     * @param  list<float>  $shares
     * @return list<int>
     */
    private function distributeByShare(int $total, array $shares): array
    {
        $count = count($shares);
        if ($count === 0 || $total <= 0) {
            return array_fill(0, $count, 0);
        }

        $shareSum = array_sum($shares);
        if ($shareSum <= 0) {
            // Equal split fallback when shares are all zero (should not happen after validation).
            $base = intdiv($total, $count);
            $remainder = $total % $count;
            $result = array_fill(0, $count, $base);
            for ($i = 0; $i < $remainder; $i++) {
                $result[$i]++;
            }

            return $result;
        }

        $raw = [];
        $floors = [];
        $remainders = [];

        foreach ($shares as $index => $share) {
            $exact = $total * ($share / $shareSum);
            $floor = (int) floor($exact);
            $raw[$index] = $exact;
            $floors[$index] = $floor;
            $remainders[$index] = $exact - $floor;
        }

        $assigned = array_sum($floors);
        $left = $total - $assigned;

        arsort($remainders);
        foreach (array_keys($remainders) as $index) {
            if ($left <= 0) {
                break;
            }
            $floors[$index]++;
            $left--;
        }

        ksort($floors);

        return array_values($floors);
    }
}
