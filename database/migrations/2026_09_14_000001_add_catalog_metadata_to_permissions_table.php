<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('display_name', 160)->nullable()->after('guard_name');
            $table->string('module', 80)->nullable()->after('display_name')->index();
            $table->string('portal', 32)->nullable()->after('module')->index();
            $table->json('portals')->nullable()->after('portal');
            $table->string('risk_level', 20)->default('normal')->after('portals')->index();
            $table->boolean('is_sensitive')->default(false)->after('risk_level')->index();
            $table->boolean('is_system')->default(true)->after('is_sensitive')->index();
            $table->string('description', 500)->nullable()->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropIndex(['module']);
            $table->dropIndex(['portal']);
            $table->dropIndex(['risk_level']);
            $table->dropIndex(['is_sensitive']);
            $table->dropIndex(['is_system']);
            $table->dropColumn([
                'display_name',
                'module',
                'portal',
                'portals',
                'risk_level',
                'is_sensitive',
                'is_system',
                'description',
            ]);
        });
    }
};
