<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(0)->change();
        });

        // Update drafts that never had an approved version snapshot to version 0
        DB::table('questions')
            ->where('status', 'draft')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('question_versions')
                    ->whereColumn('question_versions.question_id', 'questions.id');
            })
            ->update(['version' => 0]);
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->change();
        });
    }
};
