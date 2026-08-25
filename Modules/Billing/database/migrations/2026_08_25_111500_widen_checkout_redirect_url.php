<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VNPay (and similar) redirect URLs include a long SecureHash and exceed VARCHAR(255).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_checkout_sessions', function (Blueprint $table): void {
            $table->text('redirect_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('billing_checkout_sessions', function (Blueprint $table): void {
            $table->string('redirect_url')->nullable()->change();
        });
    }
};
