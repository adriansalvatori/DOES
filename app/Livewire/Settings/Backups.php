<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Backups extends Component
{
    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function createBackup(): void
    {
        $this->resetMessages();

        try {
            $exitCode = Artisan::call('db:backup');

            if ($exitCode === 0) {
                $this->successMessage = __('Respaldo generado exitosamente.');
            } else {
                $output = trim(Artisan::output());
                $this->errorMessage = __('Ocurrió un error al generar el respaldo.').($output ? " ({$output})" : '');
            }
        } catch (\Throwable $e) {
            $this->errorMessage = __('Error: ').$e->getMessage();
        }
    }

    public function downloadBackup(string $filename): ?BinaryFileResponse
    {
        $this->resetMessages();
        $safeFilename = basename($filename);
        $filePath = storage_path('app/backups/'.$safeFilename);

        if (! File::exists($filePath)) {
            $this->errorMessage = __('El archivo de respaldo no existe.');

            return null;
        }

        return response()->download($filePath);
    }

    public function deleteBackup(string $filename): void
    {
        $this->resetMessages();
        $safeFilename = basename($filename);
        $filePath = storage_path('app/backups/'.$safeFilename);

        if (File::exists($filePath)) {
            File::delete($filePath);
            $this->successMessage = __('Respaldo eliminado correctamente.');
        } else {
            $this->errorMessage = __('El archivo de respaldo ya no existe.');
        }
    }

    protected function resetMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function getBackupsProperty(): array
    {
        $backupDir = storage_path('app/backups');

        if (! File::exists($backupDir)) {
            return [];
        }

        $files = File::files($backupDir);

        $backups = array_filter($files, function (\SplFileInfo $file) {
            return str_starts_with($file->getFilename(), 'backup_');
        });

        usort($backups, function (\SplFileInfo $a, \SplFileInfo $b) {
            return $b->getMTime() <=> $a->getMTime();
        });

        return array_map(function (\SplFileInfo $file) {
            $bytes = $file->getSize();
            $formattedSize = $bytes >= 1048576
                ? number_format($bytes / 1048576, 2).' MB'
                : number_format($bytes / 1024, 2).' KB';

            $mtime = Carbon::createFromTimestamp($file->getMTime());

            $driver = 'sqlite';
            if (str_contains($file->getFilename(), '_mysql_')) {
                $driver = 'mysql';
            } elseif (str_contains($file->getFilename(), '_mariadb_')) {
                $driver = 'mariadb';
            } elseif (str_contains($file->getFilename(), '_pgsql_')) {
                $driver = 'pgsql';
            }

            return [
                'filename' => $file->getFilename(),
                'size' => $formattedSize,
                'size_bytes' => $bytes,
                'driver' => strtoupper($driver),
                'created_at' => $mtime->format('Y-m-d H:i:s'),
                'created_at_human' => $mtime->diffForHumans(),
                'timestamp' => $mtime->getTimestamp(),
            ];
        }, $backups);
    }

    public function getNextBackupTimeProperty(): string
    {
        return Carbon::tomorrow()->startOfDay()->toIso8601String();
    }

    public function render()
    {
        return view('livewire.settings.backups', [
            'backups' => $this->backups,
            'nextBackupTime' => $this->nextBackupTime,
            'totalStorageSize' => array_sum(array_column($this->backups, 'size_bytes')),
        ])->layout('components.layouts.app', ['title' => __('Respaldos de Base de Datos')]);
    }
}
