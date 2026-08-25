<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_archives', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedBigInteger('first_audit_id');
            $table->unsignedBigInteger('last_audit_id');
            $table->unsignedInteger('row_count');
            $table->string('disk', 50);
            $table->string('path', 500)->unique();
            $table->char('sha256', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_archives');
    }
};
