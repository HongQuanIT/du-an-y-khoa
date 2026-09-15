<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $names = [
            'access_audit.export',
            'access_audit.view',
            'audit_log.export',
            'billing_payment.export',
            'billing_payment.update',
            'billing_plan.create',
            'billing_price.view',
            'billing_subscription.create',
            'billing_subscription.update',
            'classroom_oversight.instructor_assign',
            'cms_menu.reorder',
            'cms_page.create',
            'cms_page.publish',
            'exam.result_export',
            'exam.result_view',
            'feature_flag.rollout',
            'feature_flag.update',
            'feature_flag.view',
            'invoice.export',
            'invoice.view',
            'learner_profile.export',
            'learner_profile.update',
            'learner_profile.view',
            'media.usage_view',
            'partner.create',
            'partner.status_update',
            'partner.update',
            'permission.update',
            'permission.view',
            'question.restore',
            'question_version.compare',
            'report.download',
            'report_schedule.view',
            'role.clone',
            'role.delete',
            'role.update',
            'role_permission.revoke',
            'role_permission.view',
            'user.update',
            'user_session.revoke',
            'user_session.view_any',
        ];

        $ids = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    public function down(): void {}
};
