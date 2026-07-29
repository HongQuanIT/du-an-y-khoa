<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // `topic_id` already exists (see create_questions_table); wire the FK now
            // that `topics` exists. Keep 1 primary topic per question for this slice.
            $table->foreign('topic_id')->references('id')->on('topics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['topic_id']);
        });
    }
};
