<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductionSeeder;
use Database\Seeders\StagingSeeder;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Seed by environment profile. Default is idempotent (no wipe).
 * Use --fresh to migrate:fresh then reseed from scratch.
 */
final class SeedEnvironmentCommand extends Command
{
    protected $signature = 'app:seed
        {target : staging|production|local}
        {--fresh : Drop all tables (migrate:fresh), re-run migrations, then seed from scratch}
        {--force : Skip confirmation when using --fresh outside local}';

    protected $description = 'Seed baseline data for staging / production / local (optional --fresh wipe)';

    /** @var array<string, class-string> */
    private const SEEDERS = [
        'staging' => StagingSeeder::class,
        'production' => ProductionSeeder::class,
        'local' => DatabaseSeeder::class,
    ];

    public function handle(): int
    {
        $target = strtolower((string) $this->argument('target'));
        $seeder = self::SEEDERS[$target] ?? null;

        if ($seeder === null) {
            throw new InvalidArgumentException(
                'target phải là: '.implode('|', array_keys(self::SEEDERS)),
            );
        }

        if ($this->option('fresh')) {
            return $this->freshSeed($target, $seeder);
        }

        $this->info("Seeding idempotent ({$target}) — không xóa database.");
        $this->call('db:seed', [
            '--class' => $seeder,
            '--force' => true,
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  class-string  $seeder
     */
    private function freshSeed(string $target, string $seeder): int
    {
        $this->warn("⚠ --fresh sẽ XÓA TOÀN BỘ dữ liệu database rồi migrate + seed ({$target}).");

        if (! $this->option('force') && ! $this->confirm('Tiếp tục?', false)) {
            $this->info('Đã hủy.');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', [
            '--seed' => true,
            '--seeder' => $seeder,
            '--force' => true,
        ]);

        $this->info("Fresh seed ({$target}) hoàn tất.");

        return self::SUCCESS;
    }
}
