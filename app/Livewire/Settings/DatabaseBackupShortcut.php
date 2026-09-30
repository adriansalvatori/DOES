<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class DatabaseBackupShortcut extends Component
{
    public bool $isBackingUp = false;

    /**
     * Trigger the creation of a database backup.
     */
    #[On('trigger-database-backup')]
    public function createBackup(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        if (! $user->isAdmin()) {
            $this->dispatch('toast', message: __('Solo los administradores pueden generar respaldos de base de datos.'));

            return;
        }

        if ($this->isBackingUp) {
            return;
        }

        $this->isBackingUp = true;

        try {
            $exitCode = Artisan::call('db:backup');

            if ($exitCode === 0) {
                $this->dispatch('toast', message: __('Respaldo de base de datos generado exitosamente.'));
                $this->dispatch('backup-created');
            } else {
                $output = trim(Artisan::output());
                $errorMsg = __('Ocurrió un error al generar el respaldo de base de datos.').($output ? " ({$output})" : '');
                $this->dispatch('toast', message: $errorMsg);
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: __('Error: ').$e->getMessage());
        } finally {
            $this->isBackingUp = false;
        }
    }

    public function render()
    {
        return view('livewire.settings.database-backup-shortcut');
    }
}
