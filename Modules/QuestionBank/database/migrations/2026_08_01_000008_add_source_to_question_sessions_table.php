<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_sessions', function (Blueprint $table) {
            // Where the session came from: custom/weak_topics/study_plan/exam/self_assessment.
            $table->string('source', 24)->default('custom')->after('status');
            $table->index(['user_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('question_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'source']);
            $table->dropColumn('source');
        });
    }
};
