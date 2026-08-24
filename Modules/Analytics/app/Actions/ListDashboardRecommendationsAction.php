<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Analytics\Models\TopicMastery;

final class ListDashboardRecommendationsAction
{
    use AsAction;

    /** @return list<array{eyebrow: string, title: string, description: string, icon: string, url: string}> */
    public function handle(User $user, int $limit = 4): array
    {
        $recommendations = TopicMastery::query()
            ->with('medicalTaxonomyNode:id,name')
            ->where('user_id', $user->getKey())
            ->where('attempts', '>=', 3)
            ->orderBy('correct_rate')
            ->limit($limit)
            ->get()
            ->map(fn (TopicMastery $mastery): array => [
                'eyebrow' => 'Chủ đề cần củng cố',
                'title' => $mastery->medicalTaxonomyNode?->name ?? 'Kiến thức y khoa',
                'description' => sprintf('%d lượt làm · chính xác %d%%', $mastery->attempts, (int) round($mastery->correct_rate)),
                'icon' => 'cardiology',
                'url' => route('qbank.create', [
                    'source' => 'weak_topics',
                    'medical_taxonomy_node_ids' => [$mastery->medical_taxonomy_node_id],
                ]),
            ]);

        if ($recommendations->isEmpty()) {
            $recommendations = collect([
                [
                    'eyebrow' => 'Bắt đầu từ đây',
                    'title' => 'Tạo phiên luyện tập đầu tiên',
                    'description' => 'Chọn chủ đề và số câu phù hợp với mục tiêu của bạn.',
                    'icon' => 'quiz',
                    'url' => route('qbank.create'),
                ],
                [
                    'eyebrow' => 'Học đều mỗi ngày',
                    'title' => 'Thiết lập kế hoạch học tập',
                    'description' => 'Chia mục tiêu lớn thành những nhiệm vụ nhỏ mỗi ngày.',
                    'icon' => 'event_note',
                    'url' => route('study-plan.index'),
                ],
                [
                    'eyebrow' => 'Ôn tập chủ động',
                    'title' => 'Xem bộ thẻ Flashcard',
                    'description' => 'Củng cố kiến thức bằng các lượt ôn ngắn.',
                    'icon' => 'style',
                    'url' => route('flashcards.index'),
                ],
            ]);
        }

        return $recommendations->take($limit)->values()->all();
    }
}
