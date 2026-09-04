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
            if (! Schema::hasColumn('questions', 'instructor_id')) {
                $table->foreignId('instructor_id')
                    ->nullable()
                    ->after('reviewer_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('questions', 'publisher_id')) {
                $table->foreignId('publisher_id')
                    ->nullable()
                    ->after('instructor_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('questions', 'published_version')) {
                $table->unsignedInteger('published_version')
                    ->nullable()
                    ->after('version');
            }

            if (! Schema::hasColumn('questions', 'rejected_by_role')) {
                $table->string('rejected_by_role', 32)
                    ->nullable()
                    ->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            if (Schema::hasColumn('questions', 'instructor_id')) {
                $table->dropConstrainedForeignId('instructor_id');
            }

            if (Schema::hasColumn('questions', 'publisher_id')) {
                $table->dropConstrainedForeignId('publisher_id');
            }

            if (Schema::hasColumn('questions', 'published_version')) {
                $table->dropColumn('published_version');
            }

            if (Schema::hasColumn('questions', 'rejected_by_role')) {
                $table->dropColumn('rejected_by_role');
            }
        });
    }
};
