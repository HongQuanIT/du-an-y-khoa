<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('name', 'reviewer')->where('guard_name', 'web')
            ->update(['portal' => 'reviewer']);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'reviewer')->where('guard_name', 'web')
            ->where('portal', 'reviewer')->update(['portal' => 'admin']);
    }
};
