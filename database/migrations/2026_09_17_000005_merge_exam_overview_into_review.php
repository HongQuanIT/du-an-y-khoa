<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $reviewId = DB::table('permissions')->where('name', 'exam.review')->where('guard_name', 'web')->value('id');
        $overviewId = DB::table('permissions')->where('name', 'exam.overview')->where('guard_name', 'web')->value('id');

        if ($reviewId === null || $overviewId === null) {
            return;
        }

        foreach (DB::table('role_has_permissions')->where('permission_id', $overviewId)->pluck('role_id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $reviewId, 'role_id' => $roleId]);
        }

        foreach (DB::table('model_has_permissions')->where('permission_id', $overviewId)->get(['model_id', 'model_type']) as $assignment) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $reviewId,
                'model_id' => $assignment->model_id,
                'model_type' => $assignment->model_type,
            ]);
        }
    }

    public function down(): void {}
};
