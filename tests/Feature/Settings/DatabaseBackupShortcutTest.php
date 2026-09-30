<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\Backups;
use App\Livewire\Settings\DatabaseBackupShortcut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class DatabaseBackupShortcutTest extends TestCase
{
    use RefreshDatabase;

    protected string $backupDir;

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

    public function test_shortcut_component_is_rendered_in_app_layout_for_authenticated_users(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertStatus(200);
        $response->assertSeeLivewire(DatabaseBackupShortcut::class);
    }

    public function test_admin_can_trigger_backup_via_shortcut_component(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $existingFiles = array_map(fn ($f) => $f->getPathname(), File::files($this->backupDir));

        $this->actingAs($admin);

        Livewire::test(DatabaseBackupShortcut::class)
            ->call('createBackup')
            ->assertDispatched('toast', message: __('Respaldo de base de datos generado exitosamente.'))
            ->assertDispatched('backup-created');

        $newFiles = array_map(fn ($f) => $f->getPathname(), File::files($this->backupDir));
        $created = array_diff($newFiles, $existingFiles);

        $this->assertNotEmpty($created);
    }

    public function test_non_admin_cannot_trigger_backup_via_shortcut_component(): void
    {
        $designer = User::factory()->create([
            'role' => UserRole::DESIGNER,
        ]);

        $this->actingAs($designer);

        Livewire::test(DatabaseBackupShortcut::class)
            ->call('createBackup')
            ->assertDispatched('toast', message: __('Solo los administradores pueden generar respaldos de base de datos.'))
            ->assertNotDispatched('backup-created');

        $files = File::files($this->backupDir);
        $this->assertEmpty($files);
    }

    public function test_guest_cannot_trigger_backup_via_shortcut_component(): void
    {
        Livewire::test(DatabaseBackupShortcut::class)
            ->call('createBackup')
            ->assertNotDispatched('backup-created');

        $files = File::files($this->backupDir);
        $this->assertEmpty($files);
    }

    public function test_backups_component_refreshes_when_backup_created_event_is_dispatched(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $this->actingAs($admin);

        $backupsTest = Livewire::test(Backups::class);

        // Dispatch the event that the shortcut fires
        $backupsTest->dispatch('backup-created');

        $backupsTest->assertStatus(200);
    }
}
