<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table): void {
                $table->id();
                $table->char('code', 2)->unique();
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('administrative_units')) {
            Schema::create('administrative_units', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('country_id')->constrained()->cascadeOnDelete();
                $table->string('code', 20);
                $table->string('name', 120);
                $table->string('type', 30)->default('province');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['country_id', 'code']);
                $table->index(['country_id', 'is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('institutions')) {
            Schema::create('institutions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('country_id')->constrained()->restrictOnDelete();
                $table->foreignId('administrative_unit_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 180);
                $table->string('short_name', 80)->nullable();
                $table->text('search_aliases')->nullable();
                $table->string('type', 40)->default('university');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['country_id', 'name']);
                $table->index(['country_id', 'administrative_unit_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('professions')) {
            Schema::create('professions', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 100);
                $table->boolean('requires_education_stage')->default(false);
                $table->boolean('defaults_to_graduated')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('education_stages')) {
            Schema::create('education_stages', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name', 80);
                $table->boolean('is_graduated')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('learner_profiles')) {
            Schema::create('learner_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('administrative_unit_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('profession_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('education_stage_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('onboarding_completed_at')->nullable();
                $table->timestamp('marketing_consent_at')->nullable();
                $table->string('utm_source', 120)->nullable();
                $table->string('utm_medium', 120)->nullable();
                $table->string('utm_campaign', 160)->nullable();
                $table->string('utm_content', 160)->nullable();
                $table->text('referrer_url')->nullable();
                $table->text('landing_page')->nullable();
                $table->timestamps();

                $table->index(['country_id', 'administrative_unit_id']);
                $table->index(['institution_id', 'profession_id']);
                // MySQL identifier max 64 chars — auto name exceeds limit.
                $table->index(
                    ['education_stage_id', 'onboarding_completed_at'],
                    'learner_profiles_edu_stage_onboarding_idx',
                );
            });
        }

        if (! Schema::hasTable('institution_requests')) {
            Schema::create('institution_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('administrative_unit_id')->nullable()->constrained()->nullOnDelete();
                $table->string('requested_name', 180);
                $table->string('status', 20)->default('pending');
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_requests');
        Schema::dropIfExists('learner_profiles');
        Schema::dropIfExists('education_stages');
        Schema::dropIfExists('professions');
        Schema::dropIfExists('institutions');
        Schema::dropIfExists('administrative_units');
        Schema::dropIfExists('countries');
    }
};
