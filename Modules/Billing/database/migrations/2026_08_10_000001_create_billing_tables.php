<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('VND');
            $table->json('entitlements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('billing_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('billing_plans')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('source', 20)->default('purchase');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('billing_redeem_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('plan_id')->nullable()->constrained('billing_plans')->nullOnDelete();
            $table->json('entitlements')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->string('type', 20)->default('promo');
            $table->timestamps();
        });

        Schema::create('billing_redeem_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('redeem_code_id')->constrained('billing_redeem_codes')->cascadeOnDelete();
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->unique(['user_id', 'redeem_code_id']);
        });

        Schema::create('billing_institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('email_domains');
            $table->foreignId('plan_id')->nullable()->constrained('billing_plans')->nullOnDelete();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('billing_institution_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('billing_institutions')->cascadeOnDelete();
            $table->string('email');
            $table->string('status', 20)->default('verified');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'institution_id']);
        });

        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('billing_subscriptions')->nullOnDelete();
            $table->string('number')->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('VND');
            $table->string('status', 20)->default('paid');
            $table->string('description');
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['user_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_institution_members');
        Schema::dropIfExists('billing_institutions');
        Schema::dropIfExists('billing_redeem_redemptions');
        Schema::dropIfExists('billing_redeem_codes');
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('billing_plans');
    }
};
