<?php

declare(strict_types=1);

use App\Support\Audit\UserAgentParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('device_type', 20)->nullable()->after('user_agent');
            $table->string('device_name', 100)->nullable()->after('device_type');
            $table->string('operating_system', 100)->nullable()->after('device_name');
            $table->string('browser', 100)->nullable()->after('operating_system');
        });

        DB::table('audit_logs')
            ->select(['id', 'user_agent'])
            ->whereNotNull('user_agent')
            ->orderBy('id')
            ->chunkById(500, function ($logs): void {
                foreach ($logs as $log) {
                    DB::table('audit_logs')
                        ->where('id', $log->id)
                        ->update(UserAgentParser::parse($log->user_agent));
                }
            });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropColumn(['device_type', 'device_name', 'operating_system', 'browser']);
        });
    }
};
