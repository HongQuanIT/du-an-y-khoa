<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'flashcard.view',
                'flashcard.create',
                'flashcard.review',
                'session.view',
                'analytics.advanced',
            ])
            ->where('guard_name', 'web')
            ->pluck('id')
            ->all();

        if ($permissionIds === []) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    public function down(): void {}
};
