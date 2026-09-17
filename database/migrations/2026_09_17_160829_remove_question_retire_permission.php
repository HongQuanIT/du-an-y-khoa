<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permission = \Spatie\Permission\Models\Permission::where('name', 'question.retire')
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            \Illuminate\Support\Facades\DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
            \Illuminate\Support\Facades\DB::table('model_has_permissions')->where('permission_id', $permission->id)->delete();
            $permission->delete();
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'question.retire',
            'guard_name' => 'web',
            'module' => 'question_bank',
        ]);
        
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
