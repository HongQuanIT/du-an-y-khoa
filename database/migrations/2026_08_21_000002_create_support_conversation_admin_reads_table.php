<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversation_admin_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('last_seen_message_id')->default(0);
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['support_conversation_id', 'admin_id'],
                'sc_admin_reads_conversation_admin_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_conversation_admin_reads');
    }
};
