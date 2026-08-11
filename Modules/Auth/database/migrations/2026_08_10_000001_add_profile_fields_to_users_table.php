<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('headline')->nullable()->after('name');
            $table->string('specialty')->nullable()->after('headline');
            $table->string('institution')->nullable()->after('specialty');
            $table->string('avatar_path')->nullable()->after('institution');
            $table->json('notification_prefs')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'headline',
                'specialty',
                'institution',
                'avatar_path',
                'notification_prefs',
            ]);
        });
    }
};
