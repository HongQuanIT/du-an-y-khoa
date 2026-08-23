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
            $table->foreignId('created_by')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['created_by', 'updated_at']);
        });

        Schema::create('question_review_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('action', 16);
            $table->json('payload')->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['question_id', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_review_requests');

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['created_by', 'updated_at']);
            $table->dropColumn('created_by');
        });
    }
};
