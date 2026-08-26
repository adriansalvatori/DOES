<?php

namespace App\Livewire;

use App\Services\TrelloSyncService;
use Livewire\Component;

class TrelloPauseToggle extends Component
{
    public bool $isPaused = false;

    public bool $showReactivationPrompt = false;

    public function mount(TrelloSyncService $service): void
    {
        $this->isPaused = $service->isPaused();
    }

    public function togglePause(TrelloSyncService $service): void
    {
        $this->isPaused = $service->togglePaused();

        if (! $this->isPaused) {
            $this->showReactivationPrompt = true;
        } else {
            $this->showReactivationPrompt = false;
        }

        $this->dispatch('trello-pause-updated', isPaused: $this->isPaused);
    }

    public function dismissPrompt(): void
    {
        $this->showReactivationPrompt = false;
    }

    public function render()
    {
        return view('livewire.trello-pause-toggle');
    }
}
