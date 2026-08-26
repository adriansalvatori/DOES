<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    protected string $backupDir;

    protected array $createdTestFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupDir = storage_path('app/backups');
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTestFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
        parent::tearDown();
    }

    public function test_database_backup_creates_file_successfully(): void
    {
        $existingFiles = array_map(fn ($f) => $f->getPathname(), File::files($this->backupDir));

        $this->artisan('db:backup')
            ->assertExitCode(0);

        $newFiles = array_map(fn ($f) => $f->getPathname(), File::files($this->backupDir));
        $created = array_diff($newFiles, $existingFiles);
        $this->createdTestFiles = array_merge($this->createdTestFiles, $created);

        $this->assertNotEmpty($created, 'Backup command should create at least one new backup file.');
    }

    public function test_database_backup_prunes_old_backups_exceeding_keep_limit(): void
    {
        File::ensureDirectoryExists($this->backupDir);

        $oldFile1 = $this->backupDir.'/backup_sqlite_2026-01-01_000000.sqlite';
        $oldFile2 = $this->backupDir.'/backup_sqlite_2026-01-02_000000.sqlite';
        $oldFile3 = $this->backupDir.'/backup_sqlite_2026-01-03_000000.sqlite';

        File::put($oldFile1, 'dummy data 1');
        touch($oldFile1, time() - 300);
        $this->createdTestFiles[] = $oldFile1;

        File::put($oldFile2, 'dummy data 2');
        touch($oldFile2, time() - 200);
        $this->createdTestFiles[] = $oldFile2;

        File::put($oldFile3, 'dummy data 3');
        touch($oldFile3, time() - 100);
        $this->createdTestFiles[] = $oldFile3;

        $initialCount = count(File::files($this->backupDir));

        $this->artisan('db:backup', ['--keep' => 2])
            ->assertExitCode(0);

        $files = File::files($this->backupDir);
        $this->assertCount(2, $files);
    }
}
