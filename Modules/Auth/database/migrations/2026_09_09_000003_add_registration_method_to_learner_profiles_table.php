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
        Schema::table('learner_profiles', function (Blueprint $table): void {
            $table->string('registration_method', 30)->default('email')->after('user_id')->index();
        });

        DB::table('social_accounts')
            ->orderBy('id')
            ->get(['user_id', 'provider'])
            ->each(function (object $account): void {
                DB::table('learner_profiles')
                    ->where('user_id', $account->user_id)
                    ->where('registration_method', 'email')
                    ->update(['registration_method' => $account->provider]);
            });
    }

    public function down(): void
    {
        Schema::table('learner_profiles', function (Blueprint $table): void {
            $table->dropIndex(['registration_method']);
            $table->dropColumn('registration_method');
        });
    }
};
