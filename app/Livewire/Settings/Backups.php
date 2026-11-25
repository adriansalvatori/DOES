<?php

namespace App\Livewire\Settings;

use App\Support\LocalizedNumber;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Backups extends Component
{
    use WithPagination;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public string $search = '';

    public int $perPage = 10;

    public function mount(): void
    {
        $user = Auth::user();
        if ($user && ! $user->isAdmin()) {
            abort(403, __('You don\'t have permission to access this section.'));
        }
    }

    #[On('backup-created')]
    public function onBackupCreated(): void
    {
        unset($this->allBackups);
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function getBackupDirectory(): string
    {
        return config('database.backup_path', storage_path('app/backups'));
    }

    public function createBackup(): void
    {
        $this->resetMessages();

        try {
            $exitCode = Artisan::call('db:backup');

            if ($exitCode === 0) {
                $this->successMessage = __('Backup created.');
                $this->dispatch('backup-created');
                $this->dispatch('toast', message: __('Backup created.'));
                $this->resetPage();
            } else {
                $output = trim(Artisan::output());
                $this->errorMessage = __('Something went wrong creating the backup.').($output ? " ({$output})" : '');
            }
        } catch (\Throwable $e) {
            $this->errorMessage = __('Error: ').$e->getMessage();
        }
    }

    public function downloadBackup(string $filename): ?BinaryFileResponse
    {
        $this->resetMessages();
        $safeFilename = basename($filename);
        $filePath = $this->getBackupDirectory().'/'.$safeFilename;

        if (! File::exists($filePath)) {
            $this->errorMessage = __('The backup file doesn\'t exist.');

            return null;
        }

        return response()->download($filePath);
    }

    public function deleteBackup(string $filename): void
    {
        $this->resetMessages();
        $safeFilename = basename($filename);
        $filePath = $this->getBackupDirectory().'/'.$safeFilename;

        if (File::exists($filePath)) {
            File::delete($filePath);
            $this->successMessage = __('Backup deleted.');
        } else {
            $this->errorMessage = __('The backup file no longer exists.');
        }
    }

    protected function resetMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    #[Computed]
    public function allBackups(): array
    {
        $backupDir = $this->getBackupDirectory();

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

        return array_values(array_map(function (\SplFileInfo $file) {
            $bytes = $file->getSize();
            $formattedSize = $bytes >= 1048576
                ? LocalizedNumber::format($bytes / 1048576, 2).' MB'
                : LocalizedNumber::format($bytes / 1024, 2).' KB';

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
        }, $backups));
    }

    #[Computed]
    public function nextBackupTime(): string
    {
        return Carbon::tomorrow()->startOfDay()->toIso8601String();
    }

    public function render()
    {
        $all = $this->allBackups;
        $search = trim($this->search);

        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $filtered = array_values(array_filter($all, function (array $backup) use ($searchLower) {
                return str_contains(mb_strtolower($backup['filename']), $searchLower)
                    || str_contains(mb_strtolower($backup['driver']), $searchLower)
                    || str_contains(mb_strtolower($backup['created_at']), $searchLower)
                    || str_contains(mb_strtolower($backup['created_at_human']), $searchLower);
            }));
        } else {
            $filtered = $all;
        }

        $perPage = max(1, (int) $this->perPage);
        $total = count($filtered);
        $currentPage = $this->getPage();
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($currentPage > $lastPage) {
            $currentPage = 1;
            $this->setPage(1);
        }

        $offset = ($currentPage - 1) * $perPage;
        $items = array_slice($filtered, $offset, $perPage);

        $paginated = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page']
        );

        return view('livewire.settings.backups', [
            'backups' => $paginated,
            'allBackups' => $all,
            'totalBackups' => count($all),
            'filteredCount' => $total,
            'nextBackupTime' => $this->nextBackupTime,
            'totalStorageSize' => array_sum(array_column($all, 'size_bytes')),
            'latestBackup' => $all[0] ?? null,
        ])->layout('components.layouts.app', ['title' => __('Database backups')]);
    }
}
