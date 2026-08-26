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

    public function test_backups_settings_page_renders_successfully(): void
    {
        $response = $this->get('/settings/backups');

        $response->assertStatus(200);
        $response->assertSeeLivewire(Backups::class);
        $response->assertSee('Respaldos de Base de Datos');
    }

    public function test_can_create_backup_from_settings_component(): void
    {
        Livewire::test(Backups::class)
            ->call('createBackup')
            ->assertSee(__('Respaldo generado exitosamente.'));

        $files = File::files($this->backupDir);
        $this->assertNotEmpty($files);
    }

    public function test_can_delete_backup_from_settings_component(): void
    {
        File::ensureDirectoryExists($this->backupDir);
        $dummyPath = $this->backupDir.'/backup_sqlite_2026-08-26_120000.sqlite';
        File::put($dummyPath, 'test content');

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

        Livewire::test(Backups::class)
            ->call('downloadBackup', 'backup_sqlite_2026-08-26_120000.sqlite')
            ->assertFileDownloaded('backup_sqlite_2026-08-26_120000.sqlite');
    }
}
