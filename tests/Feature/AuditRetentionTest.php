<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Audit\Auditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AuditRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_logs_are_archived_with_checksum_before_database_rows_are_deleted(): void
    {
        Storage::fake('audit-test');
        config()->set('audit.retention.archive_disk', 'audit-test');
        config()->set('audit.retention.archive_path', 'audit-archives');
        $user = User::factory()->create();
        $log = Auditor::record('auth.login', $user, $user);

        DB::table('audit_logs')->where('id', $log->id)->update(['created_at' => now()->subYear()]);

        $this->artisan('audit:archive', ['--before' => now()->subMonth()->toIso8601String()])
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $log->id]);
        $archive = DB::table('audit_archives')->sole();
        Storage::disk('audit-test')->assertExists($archive->path);
        $this->assertSame(64, strlen($archive->sha256));
        $this->assertSame(1, $archive->row_count);
    }
}
