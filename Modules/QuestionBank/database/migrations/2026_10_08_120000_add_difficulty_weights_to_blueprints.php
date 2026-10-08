<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blueprints', function (Blueprint $table): void {
            $table->unsignedTinyInteger('difficulty_easy_percent')->default(40)->after('total_questions');
            $table->unsignedTinyInteger('difficulty_medium_percent')->default(30)->after('difficulty_easy_percent');
            $table->unsignedTinyInteger('difficulty_hard_percent')->default(30)->after('difficulty_medium_percent');
        });
    }

    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table): void {
            $table->dropColumn([
                'difficulty_easy_percent',
                'difficulty_medium_percent',
                'difficulty_hard_percent',
            ]);
        });
    }
};
