<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->json('metadata')->nullable()->after('after');
            $table->index(['actor_id', 'created_at'], 'audit_logs_actor_created_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_actor_created_index');
            $table->dropIndex('audit_logs_action_created_index');
            $table->dropColumn('metadata');
        });
    }
};
