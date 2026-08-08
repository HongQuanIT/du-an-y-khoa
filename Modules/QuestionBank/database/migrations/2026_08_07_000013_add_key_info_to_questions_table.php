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
            $table->json('key_info')->nullable()->after('explanation');
        });

        DB::table('questions')
            ->where('stem', 'like', 'A 24-year-old man comes to the emergency department%')
            ->update([
                'key_info' => json_encode([
                    'blood-tinged sputum',
                    'three episodes of blood in his urine',
                    'linear deposits of IgG along the glomerular basement membrane',
                ], JSON_THROW_ON_ERROR),
            ]);
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn('key_info');
        });
    }
};
