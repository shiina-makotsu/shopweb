<?php

namespace App\Console\Commands;

use App\Services\DatabaseMigrationHealth;
use Illuminate\Console\Command;

class DatabaseHealthCommand extends Command
{
    protected $signature = 'shop:database-health {--fix : Run pending migrations when the schema is incomplete}';

    protected $description = 'Check whether application database migrations are complete.';

    public function handle(DatabaseMigrationHealth $health): int
    {
        if ($this->option('fix')) {
            $result = $health->repair();

            if ($result['output'] !== '') {
                $this->line(trim($result['output']));
            }

            if ($result['ok']) {
                $this->info($result['migrated'] ? 'Database schema repaired.' : 'Database schema is already complete.');

                return self::SUCCESS;
            }

            $this->error('Database schema is still incomplete: '.($result['error'] ?: implode(', ', $result['pending_after'])));

            return self::FAILURE;
        }

        $result = $health->inspect();

        if ($result['error']) {
            $this->error('Database health check failed: '.$result['error']);

            return self::FAILURE;
        }

        if ($result['ok']) {
            $this->info('Database schema is complete.');

            return self::SUCCESS;
        }

        $this->warn('Database schema has pending migrations:');

        foreach ($result['pending'] as $migration) {
            $this->line('- '.$migration);
        }

        $this->line('Run php artisan shop:database-health --fix to repair.');

        return self::FAILURE;
    }
}
