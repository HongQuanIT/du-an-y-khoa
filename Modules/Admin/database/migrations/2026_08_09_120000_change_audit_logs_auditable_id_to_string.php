<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Questions use UUID PKs; nullableMorphs() created BIGINT auditable_id and
 * truncated UUIDs on MySQL. Store morph keys as strings for both int and UUID.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            // SQLite tests: create migration already uses nullableUuidMorphs.
            return;
        }

        if ($this->auditableIdIsString()) {
            $this->ensureAuditableMorphIndex();

            return;
        }

        $this->dropAuditableMorphIndex();

        DB::statement('ALTER TABLE audit_logs MODIFY auditable_id VARCHAR(36) NULL');

        $this->ensureAuditableMorphIndex();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! $this->auditableIdIsString()) {
            return;
        }

        // UUID morph keys cannot be stored in legacy BIGINT auditable_id.
        DB::table('audit_logs')
            ->whereNotNull('auditable_id')
            ->whereRaw("auditable_id REGEXP '[^0-9]'")
            ->delete();

        $this->dropAuditableMorphIndex();

        DB::statement('ALTER TABLE audit_logs MODIFY auditable_id BIGINT UNSIGNED NULL');

        $this->ensureAuditableMorphIndex();
    }

    private function auditableIdIsString(): bool
    {
        $column = collect(DB::select('SHOW COLUMNS FROM audit_logs WHERE Field = ?', ['auditable_id']))->first();

        if ($column === null) {
            return false;
        }

        return str_contains(strtolower((string) $column->Type), 'char');
    }

    private function dropAuditableMorphIndex(): void
    {
        $indexName = $this->auditableMorphIndexName();

        if ($indexName === null) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    private function ensureAuditableMorphIndex(): void
    {
        if ($this->auditableMorphIndexName() !== null) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    private function auditableMorphIndexName(): ?string
    {
        return collect(DB::select('SHOW INDEX FROM audit_logs'))
            ->first(fn (object $index): bool => ($index->Column_name ?? null) === 'auditable_type'
                && ($index->Seq_in_index ?? null) == 1)
            ?->Key_name;
    }
};
