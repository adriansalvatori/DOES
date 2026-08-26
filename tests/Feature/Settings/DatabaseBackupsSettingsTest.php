<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Backups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class DatabaseBackupsSettingsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_backups_settings_page_renders_successfully(): void
    {
        $response = $this->get('/settings/backups');

        $response->assertStatus(200);
        $response->assertSeeLivewire(Backups::class);
        $response->assertSee('Respaldos de Base de Datos');
    }

    public function test_can_create_backup_from_settings_component(): void
    {
        $existingFiles = array_map(fn ($f) => $f->getPathname(), File::files($this->backupDir));

        Livewire::test(Backups::class)
            ->call('createBackup')
            ->assertSee(__('Respaldo generado exitosamente.'));

        $newFiles = array_map(fn ($f) => $f->getPathname(), File::files($this->backupDir));
        $created = array_diff($newFiles, $existingFiles);
        $this->createdTestFiles = array_merge($this->createdTestFiles, $created);

        $this->assertNotEmpty($created);
    }

    public function test_can_delete_backup_from_settings_component(): void
    {
        File::ensureDirectoryExists($this->backupDir);
        $dummyPath = $this->backupDir.'/backup_sqlite_2026-08-26_120000.sqlite';
        File::put($dummyPath, 'test content');
        $this->createdTestFiles[] = $dummyPath;

        Livewire::test(Backups::class)
            ->call('deleteBackup', 'backup_sqlite_2026-08-26_120000.sqlite')
            ->assertSee(__('Respaldo eliminado correctamente.'));

        $this->assertFileDoesNotExist($dummyPath);
    }

    public function test_can_download_backup_file(): void
    {
        File::ensureDirectoryExists($this->backupDir);
        $dummyPath = $this->backupDir.'/backup_sqlite_2026-08-26_120000.sqlite';
        File::put($dummyPath, 'test content');
        $this->createdTestFiles[] = $dummyPath;

        Livewire::test(Backups::class)
            ->call('downloadBackup', 'backup_sqlite_2026-08-26_120000.sqlite')
            ->assertFileDownloaded('backup_sqlite_2026-08-26_120000.sqlite');
    }
}
