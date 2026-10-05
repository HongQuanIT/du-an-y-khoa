<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notable_type', 32)->nullable();
            $table->string('notable_id', 64)->nullable();
            $table->text('body');
            $table->mediumText('body_html');
            $table->string('color', 7)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['user_id', 'notable_type', 'notable_id'],
                'notes_owner_target_index',
            );
            $table->index(['user_id', 'updated_at'], 'notes_owner_updated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
