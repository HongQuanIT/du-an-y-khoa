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
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->string('approval_status', 20)->default('approved')->after('status');
            $table->string('lifecycle_status', 20)->default('active')->after('approval_status');
            $table->index(['approval_status', 'lifecycle_status']);
        });

        DB::table('classrooms')->orderBy('id')->eachById(function (object $classroom): void {
            [$approval, $lifecycle] = match ($classroom->status) {
                'draft' => ['draft', 'active'],
                'pending_approval' => ['pending', 'active'],
                'closed' => ['approved', 'closed'],
                'archived' => ['approved', 'archived'],
                default => ['approved', 'active'],
            };

            DB::table('classrooms')->where('id', $classroom->id)->update([
                'approval_status' => $approval,
                'lifecycle_status' => $lifecycle,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropIndex(['approval_status', 'lifecycle_status']);
            $table->dropColumn(['approval_status', 'lifecycle_status']);
        });
    }
};
