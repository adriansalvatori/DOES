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
            $this->dispatch('toast', message: __('Only administrators can create database backups.'), type: 'error');

            return;
        }

        if ($this->isBackingUp) {
            return;
        }

        $this->isBackingUp = true;

        try {
            $exitCode = Artisan::call('db:backup');

            if ($exitCode === 0) {
                $this->dispatch('toast', message: __('Database backup created.'));
                $this->dispatch('backup-created');
            } else {
                $output = trim(Artisan::output());
                $errorMsg = __('Something went wrong creating the database backup.').($output ? " ({$output})" : '');
                $this->dispatch('toast', message: $errorMsg, type: 'error');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: __('Error: ').$e->getMessage(), type: 'error');
        } finally {
            $this->isBackingUp = false;
        }
    }

    public function render()
    {
        return view('livewire.settings.database-backup-shortcut');
    }
}
