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
            $table->timestamp('password_set_at')->nullable()->useCurrent()->after('password');
        });

        DB::table('users')->whereIn(
            'id',
            DB::table('learner_profiles')
                ->select('user_id')
                ->whereIn('registration_method', ['google', 'facebook']),
        )->update(['password_set_at' => null]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('password_set_at');
        });
    }
};
