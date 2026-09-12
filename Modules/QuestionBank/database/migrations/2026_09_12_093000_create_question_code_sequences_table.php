<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Counter for immutable sequential question codes (Q00001, Q00002, …).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_code_sequences', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('next_value');
            $table->timestamps();
        });

        $max = 0;
        foreach (DB::table('questions')->whereNotNull('code')->pluck('code') as $code) {
            if (preg_match('/^Q(\d+)$/', (string) $code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        DB::table('question_code_sequences')->insert([
            'id' => 1,
            'next_value' => $max + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('question_code_sequences');
    }
};
