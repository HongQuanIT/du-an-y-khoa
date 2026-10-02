<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_reviewer_flags', function (Blueprint $table): void {
            if (! Schema::hasColumn('question_reviewer_flags', 'failed_checks')) {
                $table->json('failed_checks')->nullable()->after('note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('question_reviewer_flags', function (Blueprint $table): void {
            if (Schema::hasColumn('question_reviewer_flags', 'failed_checks')) {
                $table->dropColumn('failed_checks');
            }
        });
    }
};
