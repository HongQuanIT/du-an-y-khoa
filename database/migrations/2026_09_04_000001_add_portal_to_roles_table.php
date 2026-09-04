<?php

declare(strict_types=1);

use App\Support\Enums\PortalGroup;
use App\Support\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('portal', 32)->nullable()->after('guard_name')->index();
        });

        foreach (RoleEnum::cases() as $role) {
            DB::table('roles')
                ->where('guard_name', 'web')
                ->where('name', $role->value)
                ->update(['portal' => $role->portal()->value]);
        }

        DB::table('roles')
            ->where('guard_name', 'web')
            ->whereNull('portal')
            ->update(['portal' => PortalGroup::Admin->value]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex(['portal']);
            $table->dropColumn('portal');
        });
    }
};
