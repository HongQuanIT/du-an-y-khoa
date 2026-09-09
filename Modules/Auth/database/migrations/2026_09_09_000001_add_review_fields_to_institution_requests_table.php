<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institution_requests')) {
            return;
        }

        Schema::table('institution_requests', function (Blueprint $table): void {
            $table->text('admin_note')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('admin_note')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('institution_requests')) {
            return;
        }

        Schema::table('institution_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['admin_note', 'reviewed_at']);
        });
    }
};
