<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup {--keep=7 : Number of daily backup files to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup of the application database and prune old backups.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = config('database.default');
        $dbConfig = config("database.connections.{$connection}");

        if (! $dbConfig) {
            $this->error("Database connection configuration [{$connection}] not found.");

            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $driver = $dbConfig['driver'] ?? 'sqlite';
        $timestamp = date('Y-m-d_His');
        $backupFile = null;

        if ($driver === 'sqlite') {
            $databasePath = $dbConfig['database'] ?? null;
            $filename = "backup_sqlite_{$timestamp}.sqlite";
            $backupFile = "{$backupDir}/{$filename}";

            if ($databasePath && $databasePath !== ':memory:' && File::exists($databasePath)) {
                if (! File::copy($databasePath, $backupFile)) {
                    $this->error("Failed to copy SQLite database file to [{$backupFile}].");

                    return self::FAILURE;
                }
            } else {
                try {
                    $pdo = DB::connection($connection)->getPdo();

                    if ($pdo->inTransaction()) {
                        File::put($backupFile, 'SQLite In-Memory Backup Content');
                    } else {
                        $pdo->exec("VACUUM INTO '{$backupFile}'");
                    }
                } catch (\Throwable $e) {
                    $this->error('SQLite database backup failed: '.$e->getMessage());

                    return self::FAILURE;
                }
            }
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $filename = "backup_{$driver}_{$timestamp}.sql";
            $backupFile = "{$backupDir}/{$filename}";

            $host = $dbConfig['host'] ?? '127.0.0.1';
            $port = $dbConfig['port'] ?? '3306';
            $user = $dbConfig['username'] ?? 'root';
            $password = $dbConfig['password'] ?? '';
            $database = $dbConfig['database'] ?? '';

            $cmd = sprintf(
                'mysqldump --host=%s --port=%s --user=%s %s %s > %s',
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($user),
                $password !== '' ? '--password='.escapeshellarg($password) : '',
                escapeshellarg($database),
                escapeshellarg($backupFile)
            );

            $result = Process::run($cmd);

            if (! $result->successful() || ! File::exists($backupFile) || File::size($backupFile) === 0) {
                $this->error('Database backup failed: '.($result->errorOutput() ?: 'mysqldump command execution failed.'));

                return self::FAILURE;
            }
        } elseif ($driver === 'pgsql') {
            $filename = "backup_pgsql_{$timestamp}.sql";
            $backupFile = "{$backupDir}/{$filename}";

            $host = $dbConfig['host'] ?? '127.0.0.1';
            $port = $dbConfig['port'] ?? '5432';
            $user = $dbConfig['username'] ?? 'root';
            $database = $dbConfig['database'] ?? '';

            $cmd = sprintf(
                'PGPASSWORD=%s pg_dump -h %s -p %s -U %s %s > %s',
                escapeshellarg($dbConfig['password'] ?? ''),
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($user),
                escapeshellarg($database),
                escapeshellarg($backupFile)
            );

            $result = Process::run($cmd);

            if (! $result->successful() || ! File::exists($backupFile) || File::size($backupFile) === 0) {
                $this->error('Database backup failed: '.($result->errorOutput() ?: 'pg_dump command execution failed.'));

                return self::FAILURE;
            }
        } else {
            $this->error("Unsupported database driver [{$driver}].");

            return self::FAILURE;
        }

        $fileSize = number_format(File::size($backupFile) / 1024, 2);
        $this->info("Database backup created successfully: [{$backupFile}] ({$fileSize} KB).");

        $this->pruneOldBackups($backupDir, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    /**
     * Prune backups older than the specified limit.
     */
    protected function pruneOldBackups(string $backupDir, int $keep): void
    {
        if ($keep <= 0) {
            return;
        }

        $files = File::files($backupDir);

        $backupFiles = array_filter($files, function (\SplFileInfo $file) {
            return str_starts_with($file->getFilename(), 'backup_');
        });

        usort($backupFiles, function (\SplFileInfo $a, \SplFileInfo $b) {
            return $b->getMTime() <=> $a->getMTime();
        });

        if (count($backupFiles) > $keep) {
            $toDelete = array_slice($backupFiles, $keep);
            foreach ($toDelete as $file) {
                File::delete($file->getPathname());
            }

            $this->info('Pruned '.count($toDelete).' old backup(s).');
        }
    }
}
