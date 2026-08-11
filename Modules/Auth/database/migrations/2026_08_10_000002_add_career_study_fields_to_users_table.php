<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('career_role')->nullable()->after('institution');
            $table->unsignedSmallInteger('graduation_year')->nullable()->after('career_role');
            $table->string('country', 80)->nullable()->after('graduation_year');
            $table->string('study_objective', 40)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'career_role',
                'graduation_year',
                'country',
                'study_objective',
            ]);
        });
    }
};
