<?php

declare(strict_types=1);

/**
 * Module 27 — Notification catalog.
 *
 * category: reminder | result | classroom | system | billing | support
 * audience: who typically receives (for admin broadcast targeting + UI filters)
 * preference_key: key in users.notification_prefs controlling in-app delivery
 * bypass_prefs: always deliver (security / mandatory system)
 */
return [
    'name' => 'Notification',

    'types' => [
        'session.completed' => [
            'category' => 'result',
            'audience' => 'learner',
            'label' => 'Kết quả phiên học',
            'preference_key' => 'push_reminders',
            'icon' => 'task_alt',
        ],
        'study_plan.reminder' => [
            'category' => 'reminder',
            'audience' => 'learner',
            'label' => 'Nhắc kế hoạch học',
            'preference_key' => 'push_reminders',
            'icon' => 'event',
        ],
        'streak.warning' => [
            'category' => 'reminder',
            'audience' => 'learner',
            'label' => 'Cảnh báo streak',
            'preference_key' => 'push_reminders',
            'icon' => 'local_fire_department',
        ],
        'streak.milestone' => [
            'category' => 'reminder',
            'audience' => 'learner',
            'label' => 'Cột mốc chuỗi học tập',
            'preference_key' => 'push_reminders',
            'icon' => 'local_fire_department',
        ],
        'assignment.deadline' => [
            'category' => 'classroom',
            'audience' => 'learner',
            'label' => 'Nhắc hạn bài tập',
            'preference_key' => 'push_classroom',
            'icon' => 'assignment_late',
        ],
        'daily.learning_reminder' => [
            'category' => 'reminder',
            'audience' => 'learner',
            'label' => 'Nhắc học hàng ngày',
            'preference_key' => 'push_reminders',
            'icon' => 'alarm',
        ],
        'quest.completed' => [
            'category' => 'result',
            'audience' => 'learner',
            'label' => 'Hoàn thành nhiệm vụ',
            'preference_key' => 'push_reminders',
            'icon' => 'military_tech',
        ],
        'weekly.progress' => [
            'category' => 'result',
            'audience' => 'learner',
            'label' => 'Tổng kết tuần',
            'preference_key' => 'push_reminders',
            'icon' => 'analytics',
        ],
        'achievement.unlocked' => [
            'category' => 'result',
            'audience' => 'learner',
            'label' => 'Mở khóa huy hiệu',
            'preference_key' => 'push_reminders',
            'icon' => 'workspace_premium',
        ],
        'comeback.reminder' => [
            'category' => 'reminder',
            'audience' => 'learner',
            'label' => 'Nhắc quay lại học',
            'preference_key' => 'push_reminders',
            'icon' => 'waving_hand',
        ],
        'level.up' => [
            'category' => 'result',
            'audience' => 'learner',
            'label' => 'Thăng cấp độ',
            'preference_key' => 'push_reminders',
            'icon' => 'trending_up',
        ],
        'leaderboard.overtaken' => [
            'category' => 'reminder',
            'audience' => 'learner',
            'label' => 'Thách đấu bảng xếp hạng',
            'preference_key' => 'push_reminders',
            'icon' => 'leaderboard',
        ],
        'smart.weak_topic' => [
            'category' => 'reminder',
            'audience' => 'learner',
            'label' => 'Gợi ý chuyên đề yếu',
            'preference_key' => 'push_reminders',
            'icon' => 'psychology',
        ],
        'live.upcoming' => [
            'category' => 'classroom',
            'audience' => 'learner',
            'label' => 'Live sắp bắt đầu',
            'preference_key' => 'push_classroom',
            'icon' => 'schedule',
        ],
        'live.started' => [
            'category' => 'classroom',
            'audience' => 'learner',
            'label' => 'Lớp đang live',
            'preference_key' => 'push_classroom',
            'bypass_prefs' => true,
            'icon' => 'live_tv',
        ],
        'recording.ready' => [
            'category' => 'classroom',
            'audience' => 'all',
            'label' => 'Bản ghi sẵn sàng',
            'preference_key' => 'push_classroom',
            'icon' => 'videocam',
        ],
        'classroom.invite' => [
            'category' => 'classroom',
            'audience' => 'learner',
            'label' => 'Mời vào lớp',
            'preference_key' => 'push_classroom',
            'icon' => 'group_add',
        ],
        'classroom.pending_approval' => [
            'category' => 'classroom',
            'audience' => 'admin',
            'label' => 'Lớp chờ duyệt',
            'preference_key' => null,
            'bypass_prefs' => true,
            'icon' => 'pending_actions',
        ],
        'classroom.approved' => [
            'category' => 'classroom',
            'audience' => 'instructor',
            'label' => 'Lớp được duyệt',
            'preference_key' => 'push_classroom',
            'icon' => 'verified',
        ],
        'classroom.rejected' => [
            'category' => 'classroom',
            'audience' => 'instructor',
            'label' => 'Lớp bị từ chối',
            'preference_key' => 'push_classroom',
            'icon' => 'cancel',
        ],
        'support.reply' => [
            'category' => 'support',
            'audience' => 'all',
            'label' => 'Phản hồi hỗ trợ',
            'preference_key' => 'push_support',
            'icon' => 'support_agent',
        ],
        'support.waiting' => [
            'category' => 'support',
            'audience' => 'admin',
            'label' => 'Hỗ trợ chờ xử lý',
            'preference_key' => 'push_support',
            'icon' => 'mark_unread_chat_alt',
        ],
        'contact.new' => [
            'category' => 'support',
            'audience' => 'admin',
            'label' => 'Liên hệ form mới',
            'preference_key' => null,
            'bypass_prefs' => true,
            'icon' => 'mail',
        ],
        'billing.payment' => [
            'category' => 'billing',
            'audience' => 'learner',
            'label' => 'Thanh toán',
            'preference_key' => 'push_billing',
            'icon' => 'payments',
        ],
        'subscription.changed' => [
            'category' => 'billing',
            'audience' => 'learner',
            'label' => 'Gói đăng ký',
            'preference_key' => 'push_billing',
            'icon' => 'card_membership',
        ],
        'system.broadcast' => [
            'category' => 'system',
            'audience' => 'all',
            'label' => 'Thông báo hệ thống',
            'preference_key' => null,
            'bypass_prefs' => true,
            'icon' => 'campaign',
        ],
        'system.maintenance' => [
            'category' => 'system',
            'audience' => 'all',
            'label' => 'Bảo trì',
            'preference_key' => null,
            'bypass_prefs' => true,
            'icon' => 'construction',
        ],
        'security.login' => [
            'category' => 'system',
            'audience' => 'all',
            'label' => 'Bảo mật đăng nhập',
            'preference_key' => null,
            'bypass_prefs' => true,
            'icon' => 'security',
        ],
    ],

    'default_prefs' => [
        'email_session' => true,
        'email_plan' => true,
        'email_product' => false,
        'push_reminders' => true,
        'push_classroom' => true,
        'push_support' => true,
        'push_billing' => true,
    ],

    'retention_days' => 90,

    /** Nhắc live trước giờ bắt đầu (phút). Command quét mỗi 5 phút. */
    'live_upcoming' => [
        'lead_minutes' => 30,
    ],

    /**
     * Streak = số ngày liên tiếp đạt daily goal (số câu trả lời tối thiểu / ngày).
     * Cảnh báo buổi tối nếu chưa học hôm nay.
     */
    'streak' => [
        'min_questions_per_day' => 1,
        'min_streak_to_warn' => 1,
        'warn_after_hour' => 18,
        'lookback_days' => 90,
    ],
];
