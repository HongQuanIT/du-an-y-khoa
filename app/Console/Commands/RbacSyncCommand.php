<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Rbac\PermissionRegistry;
use App\Support\Rbac\PermissionSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

final class RbacSyncCommand extends Command
{
    protected $signature = 'rbac:sync {--dry-run : Chỉ hiển thị permission còn thiếu}';

    protected $description = 'Đồng bộ permission từ catalog theo chế độ chỉ thêm, không xóa hoặc ghi đè role';

    public function handle(PermissionRegistry $registry, PermissionSynchronizer $synchronizer): int
    {
        if (! Schema::hasTable('permissions')) {
            $this->error('Bảng permissions chưa tồn tại. Hãy chạy migrate trước.');

            return self::FAILURE;
        }

        if (! Schema::hasColumns('permissions', ['portal', 'portals', 'module', 'risk_level'])) {
            $this->error('Metadata RBAC chưa được migrate. Hãy chạy php artisan migrate trước.');

            return self::FAILURE;
        }

        $guard = (string) config('rbac.guard', 'web');
        $existing = Permission::query()
            ->where('guard_name', $guard)
            ->pluck('name')
            ->all();
        $missing = array_values(array_diff($registry->names(), $existing));

        if ($this->option('dry-run')) {
            $this->info('Catalog: '.count($registry->names()).' permission; thiếu: '.count($missing).'.');
            foreach ($missing as $name) {
                $this->line('  + '.$name);
            }

            return self::SUCCESS;
        }

        $result = $synchronizer->sync();
        $this->info("Đã đồng bộ {$result['total']} permission: tạo {$result['created']}, cập nhật {$result['updated']}.");
        $this->line('Không permission hoặc liên kết role nào bị xóa.');

        return self::SUCCESS;
    }
}
