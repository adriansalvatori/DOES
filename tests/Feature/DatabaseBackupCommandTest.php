<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    protected string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupDir = storage_path('app/backups');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->backupDir)) {
            File::cleanDirectory($this->backupDir);
        }
        parent::tearDown();
    }

    public function test_database_backup_creates_file_successfully(): void
    {
        $this->artisan('db:backup')
            ->assertExitCode(0);

        $files = File::files($this->backupDir);
        $this->assertNotEmpty($files, 'Backup directory should contain at least one backup file.');

        $backupFile = $files[0];
        $this->assertStringStartsWith('backup_', $backupFile->getFilename());
    }

    public function test_database_backup_prunes_old_backups_exceeding_keep_limit(): void
    {
        File::ensureDirectoryExists($this->backupDir);

        // Create 3 dummy backup files with different modification times
        $oldFile1 = $this->backupDir.'/backup_sqlite_2026-01-01_000000.sqlite';
        $oldFile2 = $this->backupDir.'/backup_sqlite_2026-01-02_000000.sqlite';
        $oldFile3 = $this->backupDir.'/backup_sqlite_2026-01-03_000000.sqlite';

        File::put($oldFile1, 'dummy data 1');
        touch($oldFile1, time() - 300);

        File::put($oldFile2, 'dummy data 2');
        touch($oldFile2, time() - 200);

        File::put($oldFile3, 'dummy data 3');
        touch($oldFile3, time() - 100);

        // Run command keeping max 2 backups
        $this->artisan('db:backup', ['--keep' => 2])
            ->assertExitCode(0);

        $files = File::files($this->backupDir);

        // Should keep 2 latest backups (the newly generated one + the most recent old file)
        $this->assertCount(2, $files);
        $this->assertFileDoesNotExist($oldFile1);
        $this->assertFileDoesNotExist($oldFile2);
    }
}
