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
            ->with('lesson:id,name')
            ->where('user_id', $user->getKey())
            ->where('attempts', '>=', 3)
            ->orderBy('correct_rate')
            ->limit($limit)
            ->get()
            ->map(fn (TopicMastery $mastery): array => [
                'eyebrow' => 'Chủ đề cần củng cố',
                'title' => $mastery->lesson?->name ?? 'Kiến thức y khoa',
                'description' => sprintf('%d lượt làm · chính xác %d%%', $mastery->attempts, (int) round($mastery->correct_rate)),
                'icon' => 'cardiology',
                'url' => route('qbank.create', [
                    'source' => 'weak_topics',
                    'lesson_ids' => [$mastery->lesson_id],
                ]),
            ]);

        if (! $user->can('question.view')) {
            $recommendations = collect();
        }

        if ($recommendations->isEmpty()) {
            $recommendations = collect();

            if ($user->can('question.view')) {
                $recommendations->push([
                    'eyebrow' => 'Bắt đầu từ đây',
                    'title' => 'Tạo phiên luyện tập đầu tiên',
                    'description' => 'Chọn chủ đề và số câu phù hợp với mục tiêu của bạn.',
                    'icon' => 'quiz',
                    'url' => route('qbank.create'),
                ]);
            }

            if ($user->can('study_plan.view')) {
                $recommendations->push([
                    'eyebrow' => 'Học đều mỗi ngày',
                    'title' => 'Thiết lập kế hoạch học tập',
                    'description' => 'Chia mục tiêu lớn thành những nhiệm vụ nhỏ mỗi ngày.',
                    'icon' => 'event_note',
                    'url' => route('study-plan.index'),
                ]);
            }

            if ($user->can('classroom.view')) {
                $recommendations->push([
                    'eyebrow' => 'Tham gia học tập',
                    'title' => 'Lớp học trực tuyến',
                    'description' => 'Tham gia các buổi học tương tác cùng giảng viên và bạn bè.',
                    'icon' => 'cast_for_education',
                    'url' => route('classroom.index'),
                ]);
            }
        }

        return $recommendations->take($limit)->values()->all();
    }
}
