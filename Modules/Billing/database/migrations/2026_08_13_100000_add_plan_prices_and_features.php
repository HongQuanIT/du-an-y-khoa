<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_plans', function (Blueprint $table): void {
            $table->json('features')->nullable()->after('entitlements');
        });

        Schema::create('billing_plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('billing_plans')->cascadeOnDelete();
            $table->string('slug');
            $table->string('label');
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('compare_at_price_cents')->nullable();
            $table->string('currency', 3)->default('VND');
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->string('billing_type', 20)->default('prepaid');
            $table->string('badge_label')->nullable();
            $table->unsignedTinyInteger('savings_percent')->nullable();
            $table->string('cta_label')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'slug']);
            $table->index(['plan_id', 'is_public', 'sort_order']);
        });

        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->foreignId('plan_price_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('billing_plan_prices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plan_price_id');
        });

        Schema::dropIfExists('billing_plan_prices');

        Schema::table('billing_plans', function (Blueprint $table): void {
            $table->dropColumn('features');
        });
    }
};
