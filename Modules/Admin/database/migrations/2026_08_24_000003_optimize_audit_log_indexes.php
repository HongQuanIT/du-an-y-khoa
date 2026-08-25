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
            $table->index(['actor_id', 'id'], 'audit_logs_actor_cursor_index');
            $table->index(['actor_role', 'id'], 'audit_logs_actor_role_cursor_index');
            $table->index(['action', 'id'], 'audit_logs_action_cursor_index');
            $table->index(['ip', 'id'], 'audit_logs_ip_cursor_index');
            $table->index(
                ['auditable_type', 'auditable_id', 'id'],
                'audit_logs_auditable_cursor_index',
            );
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_actor_created_index');
            $table->dropIndex('audit_logs_action_created_index');
            $table->dropIndex('audit_logs_actor_role_created_index');
            $table->dropIndex('audit_logs_portal_category_created_index');
            $table->dropIndex('audit_logs_result_created_index');
            $table->dropIndex('audit_logs_session_created_index');
            $table->dropIndex('audit_logs_action_index');
            $table->dropIndex('audit_logs_auditable_type_auditable_id_index');
            $table->dropIndex('audit_logs_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['actor_id', 'created_at'], 'audit_logs_actor_created_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_index');
            $table->index(['actor_role', 'created_at'], 'audit_logs_actor_role_created_index');
            $table->index(['portal', 'category', 'created_at'], 'audit_logs_portal_category_created_index');
            $table->index(['result', 'created_at'], 'audit_logs_result_created_index');
            $table->index(['session_id', 'created_at'], 'audit_logs_session_created_index');
            $table->index('action', 'audit_logs_action_index');
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_type_auditable_id_index');
            $table->index('created_at', 'audit_logs_created_at_index');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_actor_cursor_index');
            $table->dropIndex('audit_logs_actor_role_cursor_index');
            $table->dropIndex('audit_logs_action_cursor_index');
            $table->dropIndex('audit_logs_ip_cursor_index');
            $table->dropIndex('audit_logs_auditable_cursor_index');
        });
    }
};
