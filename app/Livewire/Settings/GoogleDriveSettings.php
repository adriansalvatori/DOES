<?php

namespace App\Livewire\Settings;

use App\Services\GoogleDriveService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

class GoogleDriveSettings extends Component
{
    public ?string $testMessage = null;

    public bool $testPassed = false;

    public function mount(): void
    {
        $user = Auth::user();
        if ($user && ! $user->isAdmin()) {
            abort(403, __('No tiene permisos para acceder a esta sección.'));
        }
    }

    /**
     * Prove the connected account can reach the DOES root folder in the shared drive.
     */
    public function testConnection(GoogleDriveService $drive): void
    {
        try {
            $folder = $drive->getFile($drive->rootFolderId(), 'id,name,driveId');
            $this->testPassed = true;
            $this->testMessage = empty($folder['driveId'])
                ? __('Conexión correcta con la carpeta «:name», pero no está en una unidad compartida.', ['name' => $folder['name'] ?? ''])
                : __('Conexión correcta con la carpeta «:name».', ['name' => $folder['name'] ?? '']);
        } catch (RuntimeException $e) {
            $this->testPassed = false;
            $this->testMessage = $e->getMessage();
        }
    }

    public function disconnect(GoogleDriveService $drive): void
    {
        $drive->disconnect();
        $this->reset('testMessage', 'testPassed');

        session()->flash('success', __('Google Drive desconectado.'));
    }

    public function render(GoogleDriveService $drive): View
    {
        return view('livewire.settings.google-drive-settings', [
            'hasOAuthClient' => $drive->hasOAuthClient(),
            'rootFolderId' => $drive->rootFolderId(),
            'account' => $drive->connectedAccount(),
            'isConfigured' => $drive->isConfigured(),
            'redirectUri' => route('google-drive.callback'),
        ])->layout('components.layouts.app', ['title' => __('Google Drive')]);
    }
}
