<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ArchiveAuditLogs extends Command
{
    protected $signature = 'audit:archive
        {--before= : Chỉ archive log cũ hơn thời điểm ISO-8601 này}
        {--limit=10000 : Số dòng tối đa trong một file}
        {--dry-run : Chỉ thống kê, không ghi file hoặc xóa dữ liệu}';

    protected $description = 'Nén Audit Log cũ thành JSONL gzip có checksum rồi xóa dữ liệu nóng theo lô';

    public function handle(): int
    {
        $before = $this->option('before')
            ? Carbon::parse((string) $this->option('before'))
            : now()->subDays((int) config('audit.retention.hot_days', 180));
        $limit = min(50_000, max(1, (int) $this->option('limit')));
        $query = DB::table('audit_logs')->where('created_at', '<', $before)->orderBy('id')->limit($limit);

        if ($this->option('dry-run')) {
            $this->info('Có '.$query->count().' Audit Log đủ điều kiện archive trước '.$before->toIso8601String().'.');

            return self::SUCCESS;
        }

        $logs = $query->get();
        if ($logs->isEmpty()) {
            $this->info('Không có Audit Log cần archive.');

            return self::SUCCESS;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'medlearn-audit-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Không thể tạo file archive tạm.');
        }

        $gzip = gzopen($temporaryPath, 'wb9');
        if ($gzip === false) {
            throw new RuntimeException('Không thể mở file archive gzip.');
        }

        try {
            foreach ($logs as $log) {
                $line = json_encode($log, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
                gzwrite($gzip, $line);
            }
        } finally {
            gzclose($gzip);
        }

        $first = $logs->first();
        $last = $logs->last();
        $disk = (string) config('audit.retention.archive_disk', 'local');
        $directory = trim((string) config('audit.retention.archive_path', 'audit-archives'), '/');
        $path = sprintf(
            '%s/%s/audit-%d-%d-%s.jsonl.gz',
            $directory,
            Carbon::parse($first->created_at)->format('Y/m'),
            $first->id,
            $last->id,
            Str::uuid(),
        );
        $checksum = hash_file('sha256', $temporaryPath);
        $stream = fopen($temporaryPath, 'rb');

        try {
            if ($stream === false || ! Storage::disk($disk)->put($path, $stream)) {
                throw new RuntimeException('Không thể lưu file Audit archive. Dữ liệu database chưa bị xóa.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($temporaryPath);
        }

        DB::transaction(function () use ($logs, $first, $last, $disk, $path, $checksum): void {
            DB::table('audit_archives')->insert([
                'period_start' => $first->created_at,
                'period_end' => $last->created_at,
                'first_audit_id' => $first->id,
                'last_audit_id' => $last->id,
                'row_count' => $logs->count(),
                'disk' => $disk,
                'path' => $path,
                'sha256' => $checksum,
                'created_at' => now(),
            ]);

            DB::table('audit_logs')->whereIn('id', $logs->pluck('id'))->delete();
        });

        $this->info("Đã archive {$logs->count()} log vào {$disk}:{$path}.");

        return self::SUCCESS;
    }
}
