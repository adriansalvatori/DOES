<?php

namespace App\Livewire\Settings;

use App\Services\NavigationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SettingsIndex extends Component
{
    public function render(NavigationService $navigation): View
    {
        return view('livewire.settings.settings-index', [
            'groups' => $navigation->settingsGroups(auth()->user()),
        ])->layout('components.layouts.app', ['title' => __('Ajustes')]);
    }
}
