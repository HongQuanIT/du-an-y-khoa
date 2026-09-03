<?php

declare(strict_types=1);

namespace Modules\Notification\View\Composers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\LiveSession;
use Modules\Notification\Models\UserNotification;

final class HeaderNotificationsComposer
{
    /** Important notification types that warrant a floating flyout near the bell */
    private const IMPORTANT_TYPES = [
        'live.started',
        'live.upcoming',
        'recording.ready',
        'streak.warning',
        'streak.milestone',
        'study_plan.reminder',
        'assignment.deadline',
        'system.broadcast',
        'system.maintenance',
        'support.reply',
        'classroom.pending_approval',
        'classroom.approved',
        'classroom.rejected',
    ];

    public function compose(View $view): void
    {
        $user = Auth::user();
        if ($user === null) {
            $view->with([
                'headerNotifications' => collect(),
                'headerUnreadCount' => 0,
                'importantFlyoutNotification' => null,
            ]);

            return;
        }

        $baseQuery = UserNotification::query()->where('user_id', $user->getKey());

        $candidates = (clone $baseQuery)
            ->whereNull('read_at')
            ->whereIn('type', self::IMPORTANT_TYPES)
            ->latest()
            ->limit(10)
            ->get();

        $importantUnread = null;
        foreach ($candidates as $candidate) {
            $type = $candidate->type ?? '';

            if ($type === 'live.started') {
                $sessionId = $candidate->data['session_id'] ?? null;
                $session = $sessionId ? LiveSession::query()->find($sessionId) : null;
                if ($session === null || $session->status !== LiveSessionStatus::Live) {
                    $candidate->markRead();
                    continue;
                }
            } elseif ($type === 'live.upcoming') {
                $sessionId = $candidate->data['session_id'] ?? null;
                $session = $sessionId ? LiveSession::query()->find($sessionId) : null;
                if ($session === null || $session->status !== LiveSessionStatus::Scheduled) {
                    $candidate->markRead();
                    continue;
                }
            }

            $importantUnread = $candidate;
            break;
        }

        $importantData = null;
        if ($importantUnread !== null) {
            $type = $importantUnread->type ?? '';
            $style = match (true) {
                str_contains($type, 'streak') => [
                    'badgeClass' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
                    'icon' => 'local_fire_department',
                    'badgeLabel' => 'Chuỗi học tập',
                    'cta' => 'Tiếp tục chuỗi',
                ],
                str_contains($type, 'live.started') => [
                    'badgeClass' => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300 border border-red-200 dark:border-red-800',
                    'icon' => 'live_tv',
                    'badgeLabel' => 'Lớp đang Live',
                    'cta' => 'Vào lớp ngay',
                ],
                str_contains($type, 'live.upcoming') => [
                    'badgeClass' => 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300 border border-orange-200 dark:border-orange-800',
                    'icon' => 'schedule',
                    'badgeLabel' => 'Lớp sắp diễn ra',
                    'cta' => 'Xem phòng học',
                ],
                str_contains($type, 'recording') => [
                    'badgeClass' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800',
                    'icon' => 'videocam',
                    'badgeLabel' => 'Video bài giảng',
                    'cta' => 'Xem bài giảng',
                ],
                str_contains($type, 'study_plan') || str_contains($type, 'daily') => [
                    'badgeClass' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
                    'icon' => 'event',
                    'badgeLabel' => 'Kế hoạch học',
                    'cta' => 'Bắt đầu học',
                ],
                str_contains($type, 'support') => [
                    'badgeClass' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/50 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800',
                    'icon' => 'support_agent',
                    'badgeLabel' => 'Hỗ trợ giải đáp',
                    'cta' => 'Xem tin nhắn',
                ],
                str_contains($type, 'system') => [
                    'badgeClass' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
                    'icon' => 'campaign',
                    'badgeLabel' => 'Thông báo hệ thống',
                    'cta' => 'Xem chi tiết',
                ],
                default => [
                    'badgeClass' => 'bg-primary/10 text-primary border border-primary/20',
                    'icon' => $importantUnread->icon() ?: 'notifications',
                    'badgeLabel' => 'Thông báo quan trọng',
                    'cta' => 'Xem ngay',
                ],
            };

            $importantData = array_merge([
                'id' => $importantUnread->id,
                'title' => $importantUnread->title,
                'body' => $importantUnread->body,
                'action_url' => $importantUnread->action_url,
                'read_url' => route('notifications.read', $importantUnread),
                'created_at_human' => $importantUnread->created_at?->diffForHumans() ?? 'Vừa xong',
            ], $style);
        }

        $view->with([
            'headerNotifications' => (clone $baseQuery)->latest()->limit(8)->get(),
            'headerUnreadCount' => (clone $baseQuery)->whereNull('read_at')->count(),
            'importantFlyoutNotification' => $importantData,
        ]);
    }
}
