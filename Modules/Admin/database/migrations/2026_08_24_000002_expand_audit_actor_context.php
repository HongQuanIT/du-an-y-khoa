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
            $table->string('actor_role', 50)->nullable()->after('actor_id');
            $table->string('portal', 20)->nullable()->after('actor_role');
            $table->string('category', 30)->nullable()->after('portal');
            $table->string('result', 20)->default('success')->after('category');
            $table->string('session_id', 64)->nullable()->after('result');

            $table->index(['actor_role', 'created_at'], 'audit_logs_actor_role_created_index');
            $table->index(['portal', 'category', 'created_at'], 'audit_logs_portal_category_created_index');
            $table->index(['result', 'created_at'], 'audit_logs_result_created_index');
            $table->index(['session_id', 'created_at'], 'audit_logs_session_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_actor_role_created_index');
            $table->dropIndex('audit_logs_portal_category_created_index');
            $table->dropIndex('audit_logs_result_created_index');
            $table->dropIndex('audit_logs_session_created_index');
            $table->dropColumn(['actor_role', 'portal', 'category', 'result', 'session_id']);
        });
    }
};
