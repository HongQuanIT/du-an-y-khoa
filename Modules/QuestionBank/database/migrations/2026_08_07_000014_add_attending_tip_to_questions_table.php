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
            $table->text('attending_tip')->nullable()->after('key_info');
        });

        DB::table('questions')
            ->where('stem', 'like', 'A 24-year-old man comes to the emergency department%')
            ->update([
                'attending_tip' => 'Ho ra máu kết hợp tiểu máu, suy thận và IgG lắng đọng dạng đường thẳng dọc màng đáy cầu thận là bộ dấu hiệu điển hình của bệnh kháng màng đáy cầu thận (hội chứng Goodpasture).',
            ]);
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn('attending_tip');
        });
    }
};
