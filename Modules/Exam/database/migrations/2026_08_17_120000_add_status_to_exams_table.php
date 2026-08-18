<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('status', 16)->default('draft')->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
