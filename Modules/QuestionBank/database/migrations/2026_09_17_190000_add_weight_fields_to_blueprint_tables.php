<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blueprints') && ! Schema::hasColumn('blueprints', 'total_questions')) {
            Schema::table('blueprints', function (Blueprint $table): void {
                $table->unsignedInteger('total_questions')->nullable()->after('sort_order');
            });
        }

        if (Schema::hasTable('blueprint_sections')) {
            Schema::table('blueprint_sections', function (Blueprint $table): void {
                if (! Schema::hasColumn('blueprint_sections', 'weight_min')) {
                    $table->decimal('weight_min', 5, 2)->nullable()->after('sort_order');
                }
                if (! Schema::hasColumn('blueprint_sections', 'weight_max')) {
                    $table->decimal('weight_max', 5, 2)->nullable()->after('weight_min');
                }
            });
        }

        if (Schema::hasTable('core_clinical_topics')) {
            Schema::table('core_clinical_topics', function (Blueprint $table): void {
                if (! Schema::hasColumn('core_clinical_topics', 'weight_min')) {
                    $table->decimal('weight_min', 5, 2)->nullable()->after('sort_order');
                }
                if (! Schema::hasColumn('core_clinical_topics', 'weight_max')) {
                    $table->decimal('weight_max', 5, 2)->nullable()->after('weight_min');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('core_clinical_topics')) {
            Schema::table('core_clinical_topics', function (Blueprint $table): void {
                if (Schema::hasColumn('core_clinical_topics', 'weight_max')) {
                    $table->dropColumn('weight_max');
                }
                if (Schema::hasColumn('core_clinical_topics', 'weight_min')) {
                    $table->dropColumn('weight_min');
                }
            });
        }

        if (Schema::hasTable('blueprint_sections')) {
            Schema::table('blueprint_sections', function (Blueprint $table): void {
                if (Schema::hasColumn('blueprint_sections', 'weight_max')) {
                    $table->dropColumn('weight_max');
                }
                if (Schema::hasColumn('blueprint_sections', 'weight_min')) {
                    $table->dropColumn('weight_min');
                }
            });
        }

        if (Schema::hasTable('blueprints') && Schema::hasColumn('blueprints', 'total_questions')) {
            Schema::table('blueprints', function (Blueprint $table): void {
                $table->dropColumn('total_questions');
            });
        }
    }
};
