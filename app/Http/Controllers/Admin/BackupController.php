<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAccess;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BackupController extends Controller
{
    public function database(): StreamedResponse|BinaryFileResponse
    {
        abort_unless(AdminAccess::can('settings'), 403);

        $driver = DB::connection()->getDriverName();
        $timestamp = now()->format('Ymd-His');

        if ($driver === 'sqlite') {
            $path = Config::get('database.connections.sqlite.database');
            abort_unless(is_string($path) && is_file($path), Response::HTTP_NOT_FOUND);

            return response()->download($path, "shopweb-database-{$timestamp}.sqlite");
        }

        abort_unless(in_array($driver, ['mysql', 'mariadb'], true), Response::HTTP_NOT_IMPLEMENTED);

        return response()->streamDownload(function (): void {
            $this->writeMysqlDump();
        }, "shopweb-database-{$timestamp}.sql", [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ]);
    }

    public function uploads(): BinaryFileResponse
    {
        abort_unless(AdminAccess::can('settings'), 403);

        $source = public_path('uploads');
        File::ensureDirectoryExists(storage_path('app/private/backups'));

        $zipPath = storage_path('app/private/backups/shopweb-uploads-'.now()->format('Ymd-His').'.zip');
        $zip = new ZipArchive();

        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, Response::HTTP_INTERNAL_SERVER_ERROR);

        if (is_dir($source)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $absolutePath = $file->getPathname();
                $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($source) + 1));
                $zip->addFile($absolutePath, $relativePath);
            }
        }

        $zip->close();

        return response()
            ->download($zipPath, basename($zipPath), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    private function writeMysqlDump(): void
    {
        $pdo = DB::connection()->getPdo();

        echo "-- ShopWeb database backup\n";
        echo '-- Generated at '.now()->toDateTimeString()."\n\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($this->mysqlTables() as $table) {
            $quotedTable = $this->quoteIdentifier($table);
            $createRow = (array) DB::selectOne("SHOW CREATE TABLE {$quotedTable}");
            $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? null;

            if (! is_string($createSql)) {
                continue;
            }

            echo "DROP TABLE IF EXISTS {$quotedTable};\n";
            echo $createSql.";\n\n";

            foreach (DB::table($table)->cursor() as $row) {
                $values = (array) $row;
                $columns = collect(array_keys($values))->map(fn (string $column): string => $this->quoteIdentifier($column))->implode(', ');
                $encoded = collect($values)->map(fn ($value): string => $this->sqlValue($value, $pdo))->implode(', ');

                echo "INSERT INTO {$quotedTable} ({$columns}) VALUES ({$encoded});\n";
            }

            echo "\n";
        }

        echo "SET FOREIGN_KEY_CHECKS=1;\n";
    }

    /**
     * @return array<int, string>
     */
    private function mysqlTables(): array
    {
        return collect(DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'))
            ->map(fn ($row): string => (string) array_values((array) $row)[0])
            ->values()
            ->all();
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function sqlValue(mixed $value, \PDO $pdo): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }
}
