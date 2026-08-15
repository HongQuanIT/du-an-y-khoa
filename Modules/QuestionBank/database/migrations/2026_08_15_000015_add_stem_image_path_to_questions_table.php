<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->string('stem_image_path')->nullable()->after('stem');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn('stem_image_path');
        });
    }
};
