<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('disk_path');
            $table->string('format', 16);
            $table->string('status', 32)->default('uploaded');
            $table->json('source_headers')->nullable();
            $table->json('column_map')->nullable();
            $table->json('stats')->nullable();
            $table->string('error_report_path')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['uploaded_by', 'created_at']);
            $table->index('status');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignUuid('import_batch_id')
                ->nullable()
                ->after('cloned_from_version')
                ->constrained('question_import_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
        });
        Schema::dropIfExists('question_import_batches');
    }
};
