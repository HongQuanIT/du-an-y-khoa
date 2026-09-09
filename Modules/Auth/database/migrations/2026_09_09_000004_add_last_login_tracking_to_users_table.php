<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('last_login_method', 30)->nullable()->after('active_web_session_id');
            $table->timestamp('last_login_at')->nullable()->after('last_login_method')->index();
        });

        DB::table('social_accounts')
            ->whereNotNull('last_login_at')
            ->orderBy('last_login_at')
            ->orderBy('id')
            ->get(['user_id', 'provider', 'last_login_at'])
            ->each(function (object $account): void {
                DB::table('users')->where('id', $account->user_id)->update([
                    'last_login_method' => $account->provider,
                    'last_login_at' => $account->last_login_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['last_login_at']);
            $table->dropColumn(['last_login_method', 'last_login_at']);
        });
    }
};
