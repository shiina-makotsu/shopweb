<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DatabaseMigrationHealth
{
    /**
     * @return array{ok: bool, migrations_table: bool, pending: array<int, string>, error: string|null}
     */
    public function inspect(): array
    {
        try {
            $migrationFiles = $this->migrationFiles();

            if (! Schema::hasTable('migrations')) {
                return [
                    'ok' => $migrationFiles === [],
                    'migrations_table' => false,
                    'pending' => $migrationFiles,
                    'error' => null,
                ];
            }

            $ran = DB::table('migrations')->pluck('migration')->map(fn ($name): string => (string) $name)->all();
            $pending = array_values(array_diff($migrationFiles, $ran));

            return [
                'ok' => $pending === [],
                'migrations_table' => true,
                'pending' => $pending,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'migrations_table' => false,
                'pending' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, migrated: bool, pending_before: array<int, string>, pending_after: array<int, string>, output: string, error: string|null}
     */
    public function repair(bool $force = true): array
    {
        $before = $this->inspect();

        if (($before['error'] ?? null) !== null || $before['ok']) {
            return [
                'ok' => (bool) $before['ok'],
                'migrated' => false,
                'pending_before' => $before['pending'],
                'pending_after' => $before['pending'],
                'output' => '',
                'error' => $before['error'],
            ];
        }

        try {
            Artisan::call('migrate', ['--force' => $force]);
            $output = Artisan::output();
            $after = $this->inspect();

            return [
                'ok' => (bool) $after['ok'],
                'migrated' => true,
                'pending_before' => $before['pending'],
                'pending_after' => $after['pending'],
                'output' => $output,
                'error' => $after['error'],
            ];
        } catch (Throwable $exception) {
            Log::error('Database migration auto-repair failed.', [
                'exception' => $exception,
                'pending' => $before['pending'],
            ]);

            return [
                'ok' => false,
                'migrated' => true,
                'pending_before' => $before['pending'],
                'pending_after' => $before['pending'],
                'output' => Artisan::output(),
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    private function migrationFiles(): array
    {
        return collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->sort()
            ->values()
            ->all();
    }
}
