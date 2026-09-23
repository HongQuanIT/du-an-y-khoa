<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'content_editor')
            ->update(['portal' => 'editor']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'content_editor')
            ->update(['portal' => 'admin']);
    }
};
