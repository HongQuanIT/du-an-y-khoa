<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->uuid('event_id')->nullable()->after('id');
        });

        DB::table('audit_logs')
            ->whereNull('event_id')
            ->orderBy('id')
            ->chunkById(500, function ($logs): void {
                foreach ($logs as $log) {
                    DB::table('audit_logs')->where('id', $log->id)->update([
                        'event_id' => (string) Str::uuid(),
                    ]);
                }
            });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unique('event_id', 'audit_logs_event_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropUnique('audit_logs_event_id_unique');
            $table->dropColumn('event_id');
        });
    }
};
