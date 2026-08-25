<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_price_id')->constrained('billing_plan_prices')->cascadeOnDelete();
            $table->string('coupon_code')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('tax_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->string('currency', 3)->default('VND');
            $table->string('status', 20)->default('pending');
            $table->string('idempotency_key')->unique();
            $table->string('gateway', 30);
            $table->string('gateway_order_id')->nullable()->index();
            $table->text('redirect_url')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();
            $table->foreignId('checkout_session_id')->nullable()->constrained('billing_checkout_sessions')->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('VND');
            $table->string('method', 30);
            $table->string('status', 20)->default('pending');
            $table->string('provider', 30);
            $table->string('provider_payment_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('billing_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id');
            $table->string('event_type', 60);
            $table->json('payload');
            $table->string('status', 20)->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });

        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->boolean('cancel_at_period_end')->default(false)->after('ends_at');
            $table->timestamp('canceled_at')->nullable()->after('cancel_at_period_end');
            $table->string('provider', 30)->nullable()->after('canceled_at');
            $table->string('provider_subscription_id')->nullable()->after('provider');
            $table->foreignId('checkout_session_id')
                ->nullable()
                ->after('plan_price_id')
                ->constrained('billing_checkout_sessions')
                ->nullOnDelete();
        });

        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->foreignId('checkout_session_id')
                ->nullable()
                ->after('subscription_id')
                ->constrained('billing_checkout_sessions')
                ->nullOnDelete();
            $table->unsignedInteger('tax_cents')->default(0)->after('amount_cents');
            $table->unsignedInteger('discount_cents')->default(0)->after('tax_cents');
            $table->timestamp('paid_at')->nullable()->after('issued_at');
            $table->string('provider_invoice_id')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('checkout_session_id');
            $table->dropColumn(['tax_cents', 'discount_cents', 'paid_at', 'provider_invoice_id']);
        });

        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('checkout_session_id');
            $table->dropColumn([
                'cancel_at_period_end',
                'canceled_at',
                'provider',
                'provider_subscription_id',
            ]);
        });

        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_checkout_sessions');
    }
};
