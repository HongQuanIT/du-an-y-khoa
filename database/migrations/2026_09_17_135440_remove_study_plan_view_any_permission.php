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
        \Illuminate\Support\Facades\DB::table('model_has_permissions')
            ->whereIn('permission_id', function ($query) {
                $query->select('id')->from('permissions')->where('name', 'study_plan.view_any');
            })->delete();

        \Illuminate\Support\Facades\DB::table('role_has_permissions')
            ->whereIn('permission_id', function ($query) {
                $query->select('id')->from('permissions')->where('name', 'study_plan.view_any');
            })->delete();

        \Illuminate\Support\Facades\DB::table('permissions')->where('name', 'study_plan.view_any')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
