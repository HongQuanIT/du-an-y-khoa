<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $deprecatedPermissions = [
            'user.view_any',
            'learner_catalog.view_any',
            'role.view_any',
            'permission.view_any',
            'question_feedback.view_any',
            'exam.view_any',
            'classroom_oversight.view_any',
            'contact.view_any',
            'partner.view_any',
            'billing_subscription.view_any',
            'billing_payment.view_any',
        ];

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $deprecatedPermissions)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('model_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible: these permissions have been deprecated and normalized to *.view.
    }
};
