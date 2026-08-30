<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name');
            $table->unsignedInteger('default_commission_rate_bps')->default(1000);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('partner_invite_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->string('label')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->unsignedInteger('commission_rate_bps')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('partner_id');
        });

        Schema::create('partner_attributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('invite_code_id')->constrained('partner_invite_codes')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamp('attributed_at');
            $table->string('source', 20)->default('link');
            $table->timestamps();

            $table->index('partner_id');
        });

        Schema::create('partner_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });

        Schema::create('partner_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('attribution_id')->constrained('partner_attributions')->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained('billing_payments')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('gross_cents');
            $table->unsignedInteger('rate_bps');
            $table->unsignedBigInteger('commission_cents');
            $table->string('status', 20)->default('pending');
            $table->foreignId('payout_id')->nullable()->constrained('partner_payouts')->nullOnDelete();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_commissions');
        Schema::dropIfExists('partner_payouts');
        Schema::dropIfExists('partner_attributions');
        Schema::dropIfExists('partner_invite_codes');
        Schema::dropIfExists('partners');
    }
};
