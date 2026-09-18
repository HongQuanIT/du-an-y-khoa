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
        if (! Schema::hasTable('core_clinical_topics')) {
            return;
        }

        if (! Schema::hasColumn('core_clinical_topics', 'weight')) {
            Schema::table('core_clinical_topics', function (Blueprint $table): void {
                $table->decimal('weight', 5, 2)->nullable()->after('sort_order');
            });
        }

        if (Schema::hasColumn('core_clinical_topics', 'weight_min')) {
            DB::table('core_clinical_topics')
                ->whereNull('weight')
                ->whereNotNull('weight_min')
                ->update(['weight' => DB::raw('weight_min')]);

            Schema::table('core_clinical_topics', function (Blueprint $table): void {
                $table->dropColumn(['weight_min', 'weight_max']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('core_clinical_topics')) {
            return;
        }

        if (! Schema::hasColumn('core_clinical_topics', 'weight_min')) {
            Schema::table('core_clinical_topics', function (Blueprint $table): void {
                $table->decimal('weight_min', 5, 2)->nullable()->after('sort_order');
                $table->decimal('weight_max', 5, 2)->nullable()->after('weight_min');
            });
        }

        if (Schema::hasColumn('core_clinical_topics', 'weight')) {
            DB::table('core_clinical_topics')
                ->whereNotNull('weight')
                ->update([
                    'weight_min' => DB::raw('weight'),
                    'weight_max' => DB::raw('weight'),
                ]);

            Schema::table('core_clinical_topics', function (Blueprint $table): void {
                $table->dropColumn('weight');
            });
        }
    }
};
