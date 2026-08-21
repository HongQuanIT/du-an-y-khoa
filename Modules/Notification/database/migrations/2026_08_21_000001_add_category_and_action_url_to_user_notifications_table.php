<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->string('category', 40)->default('system')->after('type');
            $table->string('action_url', 500)->nullable()->after('data');
            $table->index(['user_id', 'category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'category', 'created_at']);
            $table->dropColumn(['category', 'action_url']);
        });
    }
};
