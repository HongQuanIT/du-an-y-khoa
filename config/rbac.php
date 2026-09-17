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
                'profile' => ['view', 'update', 'password_update', 'avatar_update', 'two_factor_toggle'],
            ],
            'user_management' => [
                'user' => ['view_any', 'view', 'create', 'delete', 'status_update', 'role_assign', 'password_reset', 'two_factor_manage'],
                'learner_catalog' => ['view_any', 'view', 'create', 'update'],
            ],
            'rbac' => [
                'role' => ['view_any', 'view', 'create'],
                'role_permission' => ['assign'],
                'permission' => ['view_any', 'view'],
            ],
            'question_bank' => [
                'question' => ['view_any', 'view', 'create', 'clone', 'update', 'delete', 'submit', 'flag', 'publish', 'reject', 'retire', 'import', 'export'],
                'question_version' => ['view', 'restore'],
                'question_feedback' => ['view_any', 'view', 'update'],
            ],
            'taxonomy' => [
                'taxonomy' => ['view'],
                'blueprint' => ['view', 'create', 'update', 'delete'],
                'curriculum' => ['view', 'create', 'update', 'delete'],
                'tag' => ['view', 'create', 'update', 'delete'],
            ],
            'exam' => [
                'exam' => ['view_any', 'view', 'create', 'update', 'delete', 'publish', 'archive'],
            ],
            'classroom' => [
                'classroom_oversight' => ['view_any', 'view', 'create_on_behalf', 'update', 'approve', 'reject', 'archive', 'schedule'],
            ],
            'content' => [
                'cms' => ['view', 'create', 'update', 'delete'],
                'media' => ['view', 'upload', 'update', 'delete'],
                'contact' => ['view_any', 'view', 'update'],
            ],
            'reporting' => [
                'report' => ['view', 'export', 'refresh'],
                'report_schedule' => ['schedule', 'update', 'delete'],
                'audit_log' => ['view'],
            ],
            'billing' => [
                'billing_plan' => ['view', 'update', 'publish', 'archive'],
                'billing_price' => ['create', 'update', 'delete'],
                'billing_subscription' => ['view_any', 'view'],
                'billing_payment' => ['view_any', 'view'],
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
                'support_conversation' => ['view', 'assign', 'reply', 'resolve'],
                'notification' => ['delete'],
            ],
        ],

        'instructor' => [
            'dashboard' => [
                'teaching_dashboard' => ['view'],
            ],
            'profile' => [
                'teach_profile' => ['view', 'update', 'password_update', 'avatar_update', 'two_factor_toggle'],
            ],
            'classroom' => [
                'classroom' => ['view', 'create', 'close', 'reopen', 'delete'],
                'classroom_settings' => ['update'],
                'classroom_session' => ['schedule', 'start', 'end'],
            ],
            'question_bank' => [
                'question' => ['view_any', 'view', 'approve', 'reject'],
            ],
            'notification' => [
                'teach_notification' => ['view'],
            ],
        ],

        'learner' => [
            'dashboard' => [
                'learner_dashboard' => ['view'],
            ],
            'account' => [
                'profile' => ['view', 'update', 'password_update', 'avatar_update', 'two_factor_toggle'],
            ],
            'question_bank' => [
                'question' => ['view'],
                'session' => ['create', 'start', 'submit', 'review', 'repeat', 'delete'],
            ],
            'study_plan' => [
                'study_plan' => ['view_any', 'view', 'create'],
                'study_plan_task' => ['start', 'complete', 'skip', 'review'],
            ],
            'learning' => [
                'search' => ['use'],
                'bookmark' => ['view', 'create', 'update', 'delete'],
                'learning_tool' => ['note', 'flag', 'highlight', 'research'],
            ],
            'classroom' => [
                'classroom' => ['view', 'join', 'leave'],
            ],
            'exam' => [
                'exam' => ['view', 'review', 'take'],
            ],
            'notification' => [
                'notification' => ['view'],
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
            'profile' => [
                'partner_profile' => ['view', 'update', 'password_update', 'avatar_update', 'two_factor_toggle'],
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
        'user.two_factor_manage',
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
