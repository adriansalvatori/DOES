<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Backups;
use App\Models\User;
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
        $this->backupDir = storage_path('framework/testing/backups');
        config(['database.backup_path' => $this->backupDir]);
        File::ensureDirectoryExists($this->backupDir);
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
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/settings/backups');

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

    public function test_backups_can_be_paginated_and_searched(): void
    {
        File::ensureDirectoryExists($this->backupDir);

        for ($i = 1; $i <= 15; $i++) {
            $day = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $filename = "backup_sqlite_2026-01-{$day}_000000.sqlite";
            $path = $this->backupDir.'/'.$filename;
            File::put($path, 'dummy content '.$i);
            touch($path, strtotime("2026-01-{$day} 12:00:00"));
        }

        Livewire::test(Backups::class)
            ->set('perPage', 5)
            ->assertSee('backup_sqlite_2026-01-15_000000.sqlite')
            ->assertSee('15 disponible(s)')
            ->call('nextPage')
            ->assertSee('backup_sqlite_2026-01-10_000000.sqlite')
            ->assertDontSee('backup_sqlite_2026-01-14_000000.sqlite')
            ->set('search', '2026-01-05')
            ->assertSee('backup_sqlite_2026-01-05_000000.sqlite')
            ->assertDontSee('backup_sqlite_2026-01-02_000000.sqlite');
    }
}
