<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RBAC permission catalog
|--------------------------------------------------------------------------
|
| Permissions are expanded to `{resource}.{action}` by PermissionRegistry.
| Keep this catalog aligned with real routes/actions; legacy permissions are
| merged automatically during the non-breaking migration period.
|
*/

return [
    'guard' => 'web',

    'catalog' => [
        'admin' => [
            'account' => [
                'profile' => ['view', 'update', 'password_update', 'avatar_update'],
            ],
            'user_management' => [
                'user' => ['view_any', 'view', 'create', 'status_update', 'role_assign', 'password_reset'],
                'learner_catalog' => ['view_any', 'create', 'update'],
            ],
            'rbac' => [
                'role' => ['view_any', 'view', 'create'],
                'role_permission' => ['assign'],
                'permission' => ['view_any'],
            ],
            'question_bank' => [
                'question' => ['view_any', 'create', 'clone', 'update', 'delete', 'submit', 'publish', 'retire', 'import', 'export'],
                'question_version' => ['view', 'restore'],
                'question_feedback' => ['view_any', 'update'],
            ],
            'taxonomy' => [
                'taxonomy' => ['view'],
                'blueprint' => ['view', 'create', 'update', 'delete'],
                'curriculum' => ['view', 'create', 'update', 'delete'],
                'tag' => ['view', 'create', 'update', 'delete'],
            ],
            'exam' => [
                'exam' => ['view_any', 'create', 'update', 'delete', 'publish', 'archive'],
            ],
            'classroom' => [
                'classroom_oversight' => ['view_any', 'approve', 'reject', 'archive', 'create_on_behalf', 'schedule'],
            ],
            'content' => [
                'cms_page' => ['view_any', 'view', 'update'],
                'cms_menu' => ['view', 'update'],
                'cms_banner' => ['view', 'create', 'update', 'delete'],
                'cms_faq' => ['view', 'create', 'update', 'delete', 'reorder'],
                'media' => ['view', 'upload', 'update', 'delete', 'import'],
                'contact' => ['view_any', 'update'],
            ],
            'reporting' => [
                'report' => ['view', 'export', 'refresh'],
                'report_schedule' => ['create', 'update', 'delete'],
                'audit_log' => ['view'],
            ],
            'billing' => [
                'billing_plan' => ['view', 'update', 'publish', 'archive'],
                'billing_price' => ['create', 'update', 'delete'],
                'billing_subscription' => ['view'],
                'billing_payment' => ['view'],
                'billing_gateway' => ['view', 'update'],
            ],
            'partner_management' => [
                'partner' => ['view_any', 'view'],
                'partner_code' => ['view', 'create', 'update'],
                'partner_payout' => ['view', 'create', 'mark_paid'],
            ],
            'system' => [
                'system_setting' => ['view', 'update', 'maintenance_toggle'],
                'notification_broadcast' => ['view', 'send'],
                'support_conversation' => ['view', 'assign', 'update', 'reply', 'resolve'],
                'notification' => ['view', 'update', 'delete'],
            ],
        ],

        'instructor' => [
            'dashboard' => [
                'teaching_dashboard' => ['view'],
            ],
            'profile' => [
                'teach_profile' => ['view', 'update', 'password_update', 'avatar_update'],
            ],
            'classroom' => [
                'classroom' => ['view', 'create', 'close', 'reopen', 'delete'],
                'classroom_settings' => ['update'],
                'classroom_member' => ['view', 'remove', 'invite', 'ban'],
                'classroom_session' => ['schedule', 'start', 'end'],
                'live_message' => ['create', 'manage'],
                'live_question' => ['view', 'update'],
                'live_hand' => ['raise', 'manage'],
                'live_chat' => ['mute'],
            ],
            'notification' => [
                'teach_notification' => ['view', 'update'],
            ],
        ],

        'learner' => [
            'dashboard' => [
                'learner_dashboard' => ['view'],
            ],
            'account' => [
                'profile' => ['view', 'update', 'password_update', 'avatar_update'],
            ],
            'question_bank' => [
                'question' => ['view'],
                'session' => ['create', 'start', 'submit', 'review', 'repeat', 'delete'],
            ],
            'study_plan' => [
                'study_plan' => ['view_any', 'view', 'create', 'update', 'delete', 'replan'],
                'study_plan_task' => ['start', 'complete', 'skip', 'review'],
            ],
            'learning' => [
                'search' => ['use'],
                'bookmark' => ['view', 'create', 'update', 'delete'],
            ],
            'classroom' => [
                'classroom' => ['view', 'join', 'leave'],
                'live_message' => ['create'],
                'live_hand' => ['raise'],
            ],
            'exam' => [
                'exam' => ['view', 'overview', 'review', 'take'],
            ],
            'subscription' => [
                'subscription' => ['view', 'checkout'],
            ],
        ],

        'partner' => [
            'access' => [
                'partner' => ['portal'],
                'partner_dashboard' => ['view'],
            ],
            'affiliate' => [
                'partner_code' => ['view'],
                'partner_referral' => ['view'],
                'partner_commission' => ['view'],
                'partner_payout' => ['view'],
            ],
        ],
    ],

    'sensitive' => [
        'user.status_update',
        'user.password_reset',
        'question.publish',
        'question.restore',
        'classroom_oversight.force_end',
        'billing_gateway.update',
        'partner_payout.mark_paid',
        'notification_broadcast.send',
        'system_setting.update',
        'system_setting.maintenance_toggle',
    ],

    'critical' => [
        'user.role_assign',
        'role.create',
        'role_permission.assign',
    ],
];
